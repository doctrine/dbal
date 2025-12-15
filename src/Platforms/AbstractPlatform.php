<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnPrecisionRequired;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnScaleRequired;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnValuesRequired;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\Exception\NoColumnsSpecifiedForTable;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DefaultUnionSQLBuilder;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\UnionSQLBuilder;
use Doctrine\DBAL\SQL\Builder\WithSQLBuilder;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function addcslashes;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function key;
use function max;
use function mb_strlen;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function strtoupper;

/**
 * Base class for all DatabasePlatforms. The DatabasePlatforms are the central
 * point of abstraction of platform-specific behaviors, features and SQL dialects.
 * They are a passive source of information.
 *
 * @phpstan-import-type ColumnProperties from Column
 * @phpstan-type CreateTableParameters = array{
 *    primary?: list<string>,
 *    primary_index?: Index,
 *    indexes?: list<Index>,
 *    uniqueConstraints?: list<UniqueConstraint>,
 *    foreignKeys?: list<ForeignKeyConstraint>,
 *    comment?: string,
 * }
 */
abstract class AbstractPlatform
{
    /** @deprecated */
    public const CREATE_INDEXES = 1;

    /** @deprecated */
    public const CREATE_FOREIGNKEYS = 2;

    /** @var string[]|null */
    protected ?array $doctrineTypeMapping = null;

    /**
     * Holds the KeywordList instance for the current platform.
     *
     * @deprecated
     */
    protected ?KeywordList $_keywords = null;

    /**
     * Defines how the platform folds the case of unquoted identifiers.
     */
    private ?UnquotedIdentifierFolding $unquotedIdentifierFolding = null;

    public function __construct(?UnquotedIdentifierFolding $unquotedIdentifierFolding = null)
    {
        if ($unquotedIdentifierFolding === null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6823',
                'Not passing $unquotedIdentifierFolding to %s() is deprecated.',
                __METHOD__,
            );
        }

        $this->unquotedIdentifierFolding = $unquotedIdentifierFolding ?? UnquotedIdentifierFolding::UPPER;
    }

    /**
     * Returns the SQL snippet that declares a boolean column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getBooleanTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares a 4 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getIntegerTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares an 8 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getBigIntTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares a 2 byte integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares common properties of an integer column.
     *
     * @param array<string, mixed> $column
     */
    abstract protected function _getCommonIntegerTypeDeclarationSQL(array $column): string;

    /**
     * Lazy load Doctrine Type Mappings.
     */
    abstract protected function initializeDoctrineTypeMappings(): void;

    /**
     * Initializes Doctrine Type Mappings with the platform defaults
     * and with all additional type mappings.
     *
     * @throws TypesException
     */
    private function initializeAllDoctrineTypeMappings(): void
    {
        $this->initializeDoctrineTypeMappings();

        foreach (Type::getTypesMap() as $typeName => $className) {
            foreach (Type::getType($typeName)->getMappedDatabaseTypes($this) as $dbType) {
                $dbType                             = strtolower($dbType);
                $this->doctrineTypeMapping[$dbType] = $typeName;
            }
        }
    }

    /**
     * Returns the SQL snippet used to declare a column that can
     * store characters in the ASCII character set
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function getAsciiStringTypeDeclarationSQL(array $column): string
    {
        return $this->getStringTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet used to declare a string column type.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function getStringTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        if (empty($column['fixed'])) {
            try {
                return $this->getVarcharTypeDeclarationSQLSnippet($length);
            } catch (InvalidColumnType $e) {
                throw InvalidColumnDeclaration::fromInvalidColumnType($column['name'], $e);
            }
        }

        return $this->getCharTypeDeclarationSQLSnippet($length);
    }

    /**
     * Returns the SQL snippet used to declare a binary string column type.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function getBinaryTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        try {
            if (empty($column['fixed'])) {
                return $this->getVarbinaryTypeDeclarationSQLSnippet($length);
            }

            return $this->getBinaryTypeDeclarationSQLSnippet($length);
        } catch (InvalidColumnType $e) {
            throw InvalidColumnDeclaration::fromInvalidColumnType($column['name'], $e);
        }
    }

    /**
     * Returns the SQL snippet to declare an ENUM column.
     *
     * Enum is a non-standard type that is especially popular in MySQL and MariaDB. By default, this method map to
     * a simple VARCHAR field which allows us to deploy it on any platform, e.g. SQLite.
     *
     * @param array<string, mixed> $column
     *
     * @throws ColumnValuesRequired If the column definition does not contain any values.
     */
    public function getEnumDeclarationSQL(array $column): string
    {
        if (! isset($column['values']) || ! is_array($column['values']) || $column['values'] === []) {
            throw ColumnValuesRequired::new($this, 'ENUM');
        }

        $length = count($column['values']) > 1
            ? max(...array_map(mb_strlen(...), $column['values']))
            : mb_strlen($column['values'][key($column['values'])]);

        if (isset($column['length'])) {
            if ($length > $column['length']) {
                throw new InvalidArgumentException(sprintf(
                    'Specified column length (%d) is less than the maximum length of provided values (%d).',
                    $column['length'],
                    $length,
                ));
            }

            $length = $column['length'];
        }

        return $this->getStringTypeDeclarationSQL(['length' => $length]);
    }

    /**
     * Returns the SQL snippet to declare a GUID/UUID column.
     *
     * By default this maps directly to a CHAR(36) and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array<string, mixed> $column The column definition.
     */
    public function getGuidTypeDeclarationSQL(array $column): string
    {
        $column['length'] = 36;
        $column['fixed']  = true;

        return $this->getStringTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet to declare a JSON column.
     *
     * By default this maps directly to a CLOB and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array<string, mixed> $column
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        if (! empty($column['jsonb'])) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6939',
                'The "jsonb" column platform option is deprecated. Use the "JSONB" type instead.',
            );
        }

        return $this->getClobTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet to declare a JSONB column.
     *
     * @param array<string, mixed> $column
     */
    public function getJsonbTypeDeclarationSQL(array $column): string
    {
        return $this->getJsonTypeDeclarationSQL($column);
    }

    /**
     * @param int|null $length The length of the column in characters
     *                         or NULL if the length should be omitted.
     */
    protected function getCharTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'CHAR';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    /**
     * @param int|null $length The length of the column in characters
     *                         or NULL if the length should be omitted.
     */
    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'VARCHAR');
        }

        return sprintf('VARCHAR(%d)', $length);
    }

    /**
     * Returns the SQL snippet used to declare a fixed length binary column type.
     *
     * @param int|null $length The length of the column in bytes
     *                         or NULL if the length should be omitted.
     */
    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        $sql = 'BINARY';

        if ($length !== null) {
            $sql .= sprintf('(%d)', $length);
        }

        return $sql;
    }

    /**
     * Returns the SQL snippet used to declare a variable length binary column type.
     *
     * @param int|null $length The length of the column in bytes
     *                         or NULL if the length should be omitted.
     */
    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'VARBINARY');
        }

        return sprintf('VARBINARY(%d)', $length);
    }

    /**
     * Returns the SQL snippet used to declare a CLOB column type.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getClobTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL Snippet used to declare a BLOB column type.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getBlobTypeDeclarationSQL(array $column): string;

    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @throws Exception If the type is not found.
     */
    public function registerDoctrineTypeMapping(string $dbType, string $doctrineType): void
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        if (! Types\Type::hasType($doctrineType)) {
            throw TypeNotFound::new($doctrineType);
        }

        $dbType                             = strtolower($dbType);
        $this->doctrineTypeMapping[$dbType] = $doctrineType;
    }

    /**
     * Gets the Doctrine type that is mapped for the given database column type.
     *
     * @throws TypesException
     */
    public function getDoctrineTypeMapping(string $dbType): string
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if (! isset($this->doctrineTypeMapping[$dbType])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown database type "%s" requested, %s may not support it.',
                $dbType,
                static::class,
            ));
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Checks if a database type is currently supported by this platform.
     *
     * @throws TypesException
     */
    public function hasDoctrineTypeMappingFor(string $dbType): bool
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        return isset($this->doctrineTypeMapping[$dbType]);
    }

    /**
     * Returns the regular expression operator.
     */
    public function getRegexpExpression(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to get the length of a text column in characters.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getLengthExpression(string $string): string
    {
        return 'LENGTH(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to get the remainder of the operation of division of dividend by divisor.
     *
     * @param string $dividend SQL expression producing the dividend.
     * @param string $divisor  SQL expression producing the divisor.
     */
    public function getModExpression(string $dividend, string $divisor): string
    {
        return 'MOD(' . $dividend . ', ' . $divisor . ')';
    }

    /**
     * Returns the SQL snippet to trim a string.
     *
     * @param string      $str  The expression to apply the trim to.
     * @param TrimMode    $mode The position of the trim.
     * @param string|null $char The char to trim, has to be quoted already. Defaults to space.
     */
    public function getTrimExpression(
        string $str,
        TrimMode $mode = TrimMode::UNSPECIFIED,
        ?string $char = null,
    ): string {
        $tokens = [];

        switch ($mode) {
            case TrimMode::UNSPECIFIED:
                break;

            case TrimMode::LEADING:
                $tokens[] = 'LEADING';
                break;

            case TrimMode::TRAILING:
                $tokens[] = 'TRAILING';
                break;

            case TrimMode::BOTH:
                $tokens[] = 'BOTH';
                break;
        }

        if ($char !== null) {
            $tokens[] = $char;
        }

        if (count($tokens) > 0) {
            $tokens[] = 'FROM';
        }

        $tokens[] = $str;

        return sprintf('TRIM(%s)', implode(' ', $tokens));
    }

    /**
     * Returns the SQL snippet to get the position of the first occurrence of the substring in the string.
     *
     * @param string      $string    SQL expression producing the string to locate the substring in.
     * @param string      $substring SQL expression producing the substring to locate.
     * @param string|null $start     SQL expression producing the position to start at.
     *                               Defaults to the beginning of the string.
     */
    abstract public function getLocateExpression(string $string, string $substring, ?string $start = null): string;

    /**
     * Returns an SQL snippet to get a substring inside the string.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string      $string SQL expression producing the string from which a substring should be extracted.
     * @param string      $start  SQL expression producing the position to start at,
     * @param string|null $length SQL expression producing the length of the substring portion to be returned.
     *                            By default, the entire substring is returned.
     */
    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s FROM %s)', $string, $start);
        }

        return sprintf('SUBSTRING(%s FROM %s FOR %s)', $string, $start, $length);
    }

    /**
     * Returns a SQL snippet to concatenate the given strings.
     */
    public function getConcatExpression(string ...$string): string
    {
        return implode(' || ', $string);
    }

    /**
     * Returns the SQL to calculate the difference in days between the two passed dates.
     *
     * Computes diff = date1 - date2.
     */
    abstract public function getDateDiffExpression(string $date1, string $date2): string;

    /**
     * Returns the SQL to add the number of given seconds to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     */
    public function getDateAddSecondsExpression(string $date, string $seconds): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to subtract the number of given seconds from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     */
    public function getDateSubSecondsExpression(string $date, string $seconds): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to add the number of given minutes to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     */
    public function getDateAddMinutesExpression(string $date, string $minutes): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to subtract the number of given minutes from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     */
    public function getDateSubMinutesExpression(string $date, string $minutes): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to add the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     */
    public function getDateAddHourExpression(string $date, string $hours): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to subtract the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     */
    public function getDateSubHourExpression(string $date, string $hours): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to add the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     */
    public function getDateAddDaysExpression(string $date, string $days): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to subtract the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     */
    public function getDateSubDaysExpression(string $date, string $days): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to add the number of given weeks to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     */
    public function getDateAddWeeksExpression(string $date, string $weeks): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to subtract the number of given weeks from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     */
    public function getDateSubWeeksExpression(string $date, string $weeks): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to add the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     */
    public function getDateAddMonthExpression(string $date, string $months): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to subtract the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     */
    public function getDateSubMonthExpression(string $date, string $months): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to add the number of given quarters to a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     */
    public function getDateAddQuartersExpression(string $date, string $quarters): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to subtract the number of given quarters from a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     */
    public function getDateSubQuartersExpression(string $date, string $quarters): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to add the number of given years to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     */
    public function getDateAddYearsExpression(string $date, string $years): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $years, DateIntervalUnit::YEAR);
    }

    /**
     * Returns the SQL to subtract the number of given years from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     */
    public function getDateSubYearsExpression(string $date, string $years): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $years, DateIntervalUnit::YEAR);
    }

    /**
     * Returns the SQL for a date arithmetic expression.
     *
     * @param string           $date     SQL expression representing a date to perform the arithmetic operation on.
     * @param string           $operator The arithmetic operator (+ or -).
     * @param string           $interval SQL expression representing the value of the interval that shall be calculated
     *                                   into the date.
     * @param DateIntervalUnit $unit     The unit of the interval that shall be calculated into the date.
     */
    abstract protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string;

    /**
     * Generates the SQL expression which represents the given date interval multiplied by a number
     *
     * @param string $interval   SQL expression describing the interval value
     * @param int    $multiplier Interval multiplier
     */
    protected function multiplyInterval(string $interval, int $multiplier): string
    {
        return sprintf('(%s * %d)', $interval, $multiplier);
    }

    /**
     * Returns the SQL bit AND comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function getBitAndComparisonExpression(string $value1, string $value2): string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * Returns the SQL bit OR comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function getBitOrComparisonExpression(string $value1, string $value2): string
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    /**
     * Returns the SQL expression which represents the currently selected database.
     */
    abstract public function getCurrentDatabaseExpression(): string;

    /**
     * Honors that some SQL vendors such as MsSql use table hints for locking instead of the
     * ANSI SQL FOR UPDATE specification.
     *
     * @param string $fromClause The FROM clause to append the hint for the given lock mode to
     */
    public function appendLockHint(string $fromClause, LockMode $lockMode): string
    {
        return $fromClause;
    }

    /**
     * Returns the SQL snippet to drop an existing table.
     */
    public function getDropTableSQL(string $table): string
    {
        return 'DROP TABLE ' . $table;
    }

    /**
     * Returns the SQL to safely drop a temporary table WITHOUT implicitly committing an open transaction.
     */
    public function getDropTemporaryTableSQL(string $table): string
    {
        return $this->getDropTableSQL($table);
    }

    /**
     * Returns the SQL to drop an index from a table.
     */
    public function getDropIndexSQL(string $name, string $table): string
    {
        return 'DROP INDEX ' . $name;
    }

    /**
     * Returns the SQL to drop a constraint.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    protected function getDropConstraintSQL(string $name, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $name;
    }

    /**
     * Returns the SQL to drop a foreign key.
     */
    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $foreignKey;
    }

    /**
     * Returns the SQL to drop a unique constraint.
     */
    public function getDropUniqueConstraintSQL(string $name, string $tableName): string
    {
        return $this->getDropConstraintSQL($name, $tableName);
    }

    /**
     * Returns the SQL statement(s) to create a table with the specified name, columns and constraints
     * on this platform.
     *
     * @return list<string> The list of SQL statements.
     */
    public function getCreateTableSQL(Table $table): array
    {
        return $this->buildCreateTableSQL($table, true);
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return new DefaultSelectSQLBuilder($this, 'FOR UPDATE', 'SKIP LOCKED');
    }

    public function createUnionSQLBuilder(): UnionSQLBuilder
    {
        return new DefaultUnionSQLBuilder($this);
    }

    public function createWithSQLBuilder(): WithSQLBuilder
    {
        return new WithSQLBuilder();
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    final protected function getCreateTableWithoutForeignKeysSQL(Table $table): array
    {
        return $this->buildCreateTableSQL($table, false);
    }

    /** @return list<string> */
    private function buildCreateTableSQL(Table $table, bool $createForeignKeys): array
    {
        if (count($table->getColumns()) === 0) {
            throw NoColumnsSpecifiedForTable::new($table->getName());
        }

        $tableName                    = $table->getQuotedName($this);
        $options                      = $table->getOptions();
        $options['primary']           = [];
        $options['indexes']           = [];
        $options['uniqueConstraints'] = [];
        $options['foreignKeys']       = [];

        foreach ($table->getIndexes() as $index) {
            if (! $index->isPrimary()) {
                $options['indexes'][] = $index;

                continue;
            }

            $options['primary']       = $index->getQuotedColumns($this);
            $options['primary_index'] = $index;
        }

        foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
            $options['uniqueConstraints'][] = $uniqueConstraint;
        }

        if ($createForeignKeys) {
            foreach ($table->getForeignKeys() as $fkConstraint) {
                $options['foreignKeys'][] = $fkConstraint;
            }
        }

        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columns[] = $this->columnToArray($column);
        }

        $sql = $this->_getCreateTableSQL($tableName, $columns, $options);

        if ($this->supportsCommentOnStatement()) {
            if ($table->hasOption('comment')) {
                $sql[] = $this->getCommentOnTableSQL($tableName, $table->getOption('comment'));
            }

            foreach ($table->getColumns() as $column) {
                $comment = $column->getComment();

                if ($comment === '') {
                    continue;
                }

                $sql[] = $this->getCommentOnColumnSQL($tableName, $column->getQuotedName($this), $comment);
            }
        }

        return $sql;
    }

    /**
     * @param array<Table> $tables
     *
     * @return list<string>
     */
    public function getCreateTablesSQL(array $tables): array
    {
        $sql = [];

        foreach ($tables as $table) {
            $sql = array_merge($sql, $this->getCreateTableWithoutForeignKeysSQL($table));
        }

        foreach ($tables as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                $sql[] = $this->getCreateForeignKeySQL(
                    $foreignKey,
                    $table->getQuotedName($this),
                );
            }
        }

        return $sql;
    }

    /**
     * @param array<Table> $tables
     *
     * @return list<string>
     */
    public function getDropTablesSQL(array $tables): array
    {
        $sql = [];

        foreach ($tables as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                $sql[] = $this->getDropForeignKeySQL(
                    $foreignKey->getQuotedName($this),
                    $table->getQuotedName($this),
                );
            }
        }

        foreach ($tables as $table) {
            $sql[] = $this->getDropTableSQL($table->getQuotedName($this));
        }

        return $sql;
    }

    protected function getCommentOnTableSQL(string $tableName, string $comment): string
    {
        $tableName = new Identifier($tableName);

        return sprintf(
            'COMMENT ON TABLE %s IS %s',
            $tableName->getQuotedName($this),
            $this->quoteStringLiteral($comment),
        );
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getCommentOnColumnSQL(string $tableName, string $columnName, string $comment): string
    {
        $tableName  = new Identifier($tableName);
        $columnName = new Identifier($columnName);

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s',
            $tableName->getQuotedName($this),
            $columnName->getQuotedName($this),
            $this->quoteStringLiteral($comment),
        );
    }

    /**
     * Returns the SQL to create inline comment on a column.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function getInlineColumnCommentSQL(string $comment): string
    {
        if (! $this->supportsInlineColumnComments()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'COMMENT ' . $this->quoteStringLiteral($comment);
    }

    /**
     * Returns the SQL used to create a table.
     *
     * @param list<ColumnProperties> $columns
     * @param CreateTableParameters  $options
     *
     * @return list<string>
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $this->validateCreateTableOptions($options, __METHOD__);

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
            }
        }

        if (! empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (! empty($options['indexes'])) {
            foreach ($options['indexes'] as $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($definition);
            }
        }

        $query = 'CREATE TABLE ' . $name . ' (' . $columnListSql;
        $check = $this->getCheckDeclarationSQL($columns);

        if (! empty($check)) {
            $query .= ', ' . $check;
        }

        $query .= ')';

        $sql = [$query];

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $name);
            }
        }

        return $sql;
    }

    /**
     * @internal
     *
     * @param CreateTableParameters $options
     */
    final protected function validateCreateTableOptions(array $options, string $methodName): void
    {
        if (
            isset(
                $options['primary'],
                $options['indexes'],
                $options['uniqueConstraints'],
                $options['foreignKeys'],
            )
        ) {
            return;
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6805',
            'Not passing $options or any of its following keys to %s() is deprecated:'
                . ' "primary", "indexes", "uniqueConstraints", "foreignKeys".',
            $methodName,
        );
    }

    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE TEMPORARY TABLE';
    }

    /**
     * Generates SQL statements that can be used to apply the diff.
     *
     * @return list<string>
     */
    public function getAlterSchemaSQL(SchemaDiff $diff): array
    {
        $sql = [];

        if ($this->supportsSchemas()) {
            foreach ($diff->getCreatedSchemas() as $schema) {
                $sql[] = $this->getCreateSchemaSQL($schema);
            }
        }

        if ($this->supportsSequences()) {
            foreach ($diff->getAlteredSequences() as $sequence) {
                $sql[] = $this->getAlterSequenceSQL($sequence);
            }

            foreach ($diff->getDroppedSequences() as $sequence) {
                $sql[] = $this->getDropSequenceSQL($sequence->getQuotedName($this));
            }

            foreach ($diff->getCreatedSequences() as $sequence) {
                $sql[] = $this->getCreateSequenceSQL($sequence);
            }
        }

        $sql = array_merge(
            $sql,
            $this->getCreateTablesSQL(
                $diff->getCreatedTables(),
            ),
            $this->getDropTablesSQL(
                $diff->getDroppedTables(),
            ),
        );

        foreach ($diff->getAlteredTables() as $tableDiff) {
            $sql = array_merge($sql, $this->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }

    /**
     * Returns the SQL to create a sequence on this platform.
     */
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to change a sequence on this platform.
     */
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to drop an existing sequence.
     */
    public function getDropSequenceSQL(string $name): string
    {
        if (! $this->supportsSequences()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'DROP SEQUENCE ' . $name;
    }

    /**
     * Returns the SQL to create an index on a table on this platform.
     */
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $name    = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException(sprintf(
                'Incomplete or invalid index definition %s on table %s',
                $name,
                $table,
            ));
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        $query  = 'CREATE ' . $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ON ' . $table;
        $query .= ' (' . implode(', ', $index->getQuotedColumns($this)) . ')' . $this->getPartialIndexSQL($index);

        return $query;
    }

    /**
     * Adds condition for partial index.
     */
    protected function getPartialIndexSQL(Index $index): string
    {
        if ($this->supportsPartialIndexes() && $index->hasOption('where')) {
            return ' WHERE ' . $index->getOption('where');
        }

        return '';
    }

    /**
     * Adds additional flags for index generation.
     */
    protected function getCreateIndexSQLFlags(Index $index): string
    {
        return $index->isUnique() ? 'UNIQUE ' : '';
    }

    /**
     * Returns the SQL to create an unnamed primary key constraint.
     *
     * @deprecated
     */
    public function getCreatePrimaryKeySQL(Index $index, string $table): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6867',
            '%s() is deprecated.',
            __METHOD__,
        );

        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . implode(', ', $index->getQuotedColumns($this)) . ')';
    }

    /**
     * Returns the SQL to create a named schema.
     */
    public function getCreateSchemaSQL(string $schemaName): string
    {
        if (! $this->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'CREATE SCHEMA ' . $schemaName;
    }

    /**
     * Returns the SQL to create a unique constraint on a table on this platform.
     */
    public function getCreateUniqueConstraintSQL(UniqueConstraint $constraint, string $tableName): string
    {
        return 'ALTER TABLE ' . $tableName . ' ADD ' . $this->getUniqueConstraintDeclarationSQL($constraint);
    }

    /**
     * Returns the SQL snippet to drop a schema.
     */
    public function getDropSchemaSQL(string $schemaName): string
    {
        if (! $this->supportsSchemas()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'DROP SCHEMA ' . $schemaName;
    }

    /**
     * Quotes a string so that it can be safely used as a table or column name,
     * even if it is a reserved word of the platform. This also detects identifier
     * chains separated by dot and quotes them independently.
     *
     * NOTE: Just because you CAN use quoted identifiers doesn't mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @deprecated Use {@link quoteSingleIdentifier()} individually for each part of a qualified name instead.
     *
     * @param string $identifier The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteIdentifier(string $identifier): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6590',
            <<<'DEPRECATION'
            Method %s is deprecated and will be removed in 5.0.
            Use quoteSingleIdentifier() individually for each part of a qualified name instead.
            DEPRECATION,
            __METHOD__,
        );

        if (str_contains($identifier, '.')) {
            $parts = array_map($this->quoteSingleIdentifier(...), explode('.', $identifier));

            return implode('.', $parts);
        }

        return $this->quoteSingleIdentifier($identifier);
    }

    /**
     * Quotes a single identifier (no dot chain separation).
     *
     * @param string $str The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteSingleIdentifier(string $str): string
    {
        return '"' . str_replace('"', '""', $str) . '"';
    }

    /**
     * Returns the SQL to create a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key constraint.
     * @param string               $table      The name of the table on which the foreign key is to be created.
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' ADD ' . $this->getForeignKeyDeclarationSQL($foreignKey);
    }

    /**
     * Gets the SQL statements for altering an existing table.
     *
     * This method returns an array of SQL statements, since some platforms need several statements.
     *
     * @return list<string>
     */
    abstract public function getAlterTableSQL(TableDiff $diff): array;

    public function getRenameTableSQL(string $oldName, string $newName): string
    {
        return sprintf('ALTER TABLE %s RENAME TO %s', $oldName, $newName);
    }

    /** @return list<string> */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $tableNameSQL = $diff->getOldTable()->getQuotedName($this);

        $sql = [];

        foreach ($diff->getDroppedForeignKeys() as $foreignKey) {
            $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableNameSQL);
        }

        foreach ($diff->getModifiedForeignKeys() as $foreignKey) {
            $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableNameSQL);
        }

        foreach ($diff->getDroppedIndexes() as $index) {
            $sql[] = $this->getDropIndexSQL($index->getQuotedName($this), $tableNameSQL);
        }

        foreach ($diff->getModifiedIndexes() as $index) {
            $sql[] = $this->getDropIndexSQL($index->getQuotedName($this), $tableNameSQL);
        }

        return $sql;
    }

    /** @return list<string> */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql = [];

        $tableNameSQL = $diff->getOldTable()->getQuotedName($this);

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getModifiedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getModifiedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getRenamedIndexes() as $oldIndexName => $index) {
            $oldIndexName = new Identifier($oldIndexName);
            $sql          = array_merge(
                $sql,
                $this->getRenameIndexSQL($oldIndexName->getQuotedName($this), $index, $tableNameSQL),
            );
        }

        return $sql;
    }

    /**
     * Returns the SQL for renaming an index on a table.
     *
     * @param string $oldIndexName The name of the index to rename from.
     * @param Index  $index        The definition of the index to rename to.
     * @param string $tableName    The table to rename the given index on.
     *
     * @return list<string> The sequence of SQL statements for renaming the given index.
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return [
            $this->getDropIndexSQL($oldIndexName, $tableName),
            $this->getCreateIndexSQL($index, $tableName),
        ];
    }

    /**
     * Returns the SQL for renaming a column
     *
     * @param string $tableName     The table to rename the column on.
     * @param string $oldColumnName The name of the column we want to rename.
     * @param string $newColumnName The name we should rename it to.
     *
     * @return list<string> The sequence of SQL statements for renaming the given column.
     */
    protected function getRenameColumnSQL(string $tableName, string $oldColumnName, string $newColumnName): array
    {
        return [sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $tableName, $oldColumnName, $newColumnName)];
    }

    /**
     * Gets declaration of a number of columns in bulk.
     *
     * @param list<ColumnProperties> $columns The properties of the columns to be declared.
     */
    public function getColumnDeclarationListSQL(array $columns): string
    {
        $declarations = [];

        foreach ($columns as $column) {
            $declarations[] = $this->getColumnDeclarationSQL($column['name'], $column);
        }

        return implode(', ', $declarations);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to declare a generic type
     * column to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string           $name   The name of the column to be declared.
     * @param ColumnProperties $column Column properties.
     *
     * @return string DBMS specific SQL code portion that should be used to declare the column.
     */
    public function getColumnDeclarationSQL(string $name, array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $column['columnDefinition'];
        } else {
            $default = $this->getDefaultValueDeclarationSQL($column);

            $charset = ! empty($column['charset']) ?
                ' ' . $this->getColumnCharsetDeclarationSQL($column['charset']) : '';

            $collation = ! empty($column['collation']) ?
                ' ' . $this->getColumnCollationDeclarationSQL($column['collation']) : '';

            $notnull = ! empty($column['notnull']) ? ' NOT NULL' : '';

            $typeDecl    = $column['type']->getSQLDeclaration($column, $this);
            $declaration = $typeDecl . $charset . $default . $notnull . $collation;

            if ($this->supportsInlineColumnComments() && isset($column['comment']) && $column['comment'] !== '') {
                $declaration .= ' ' . $this->getInlineColumnCommentSQL($column['comment']);
            }
        }

        return $name . ' ' . $declaration;
    }

    /**
     * Returns the SQL snippet that declares a floating point column of arbitrary precision.
     *
     * @param array<string, mixed> $column
     */
    public function getDecimalTypeDeclarationSQL(array $column): string
    {
        if (! isset($column['precision'])) {
            throw InvalidColumnDeclaration::fromInvalidColumnType($column['name'], ColumnPrecisionRequired::new());
        }

        if (! isset($column['scale'])) {
            throw InvalidColumnDeclaration::fromInvalidColumnType($column['name'], ColumnScaleRequired::new());
        }

        return 'NUMERIC(' . $column['precision'] . ', ' . $column['scale'] . ')';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param array<string, mixed> $column The column definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    public function getDefaultValueDeclarationSQL(array $column): string
    {
        if (! isset($column['default'])) {
            return empty($column['notnull']) ? ' DEFAULT NULL' : '';
        }

        $default = $column['default'];

        if ($default instanceof DefaultExpression) {
            return ' DEFAULT ' . $default->toSQL($this);
        }

        if (! isset($column['type'])) {
            return " DEFAULT '" . $default . "'";
        }

        $type = $column['type'];

        if ($type instanceof Types\PhpIntegerMappingType) {
            return ' DEFAULT ' . $default;
        }

        if ($type instanceof Types\PhpDateTimeMappingType && $default === $this->getCurrentTimestampSQL()) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7195',
                'Using "%s" as a column default value is deprecated. Use a CurrentTimestamp instance instead.',
                $default,
            );

            return ' DEFAULT ' . $this->getCurrentTimestampSQL();
        }

        if ($type instanceof Types\PhpTimeMappingType && $default === $this->getCurrentTimeSQL()) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7195',
                'Using "%s" as a column default value is deprecated. Use a CurrentTime instance instead.',
                $default,
            );

            return ' DEFAULT ' . $this->getCurrentTimeSQL();
        }

        if ($type instanceof Types\PhpDateMappingType && $default === $this->getCurrentDateSQL()) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/7195',
                'Using "%s" as a column default value is deprecated. Use a CurrentDate instance instead.',
                $default,
            );

            return ' DEFAULT ' . $this->getCurrentDateSQL();
        }

        if ($type instanceof Types\BooleanType) {
            return ' DEFAULT ' . $this->convertBooleans($default);
        }

        if (is_int($default) || is_float($default)) {
            return ' DEFAULT ' . $default;
        }

        return ' DEFAULT ' . $this->quoteStringLiteral($default);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a CHECK constraint
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param list<string|ColumnProperties> $definition The check definition.
     *
     * @return string DBMS specific SQL code portion needed to set a CHECK constraint.
     */
    public function getCheckDeclarationSQL(array $definition): string
    {
        $constraints = [];
        foreach ($definition as $def) {
            if (is_string($def)) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6805',
                    'Passing column definition to %s() as string is deprecated. Pass the definition as array'
                        . ' instead.',
                    __METHOD__,
                );

                $constraints[] = 'CHECK (' . $def . ')';
            } else {
                if (isset($def['min'])) {
                    $constraints[] = 'CHECK (' . $def['name'] . ' >= ' . $def['min'] . ')';
                }

                if (! isset($def['max'])) {
                    continue;
                }

                $constraints[] = 'CHECK (' . $def['name'] . ' <= ' . $def['max'] . ')';
            }
        }

        return implode(', ', $constraints);
    }

    /**
     * Obtains DBMS specific DDL fragment that defines a unique constraint to be used in statements like <code>CREATE
     * TABLE</code> or <code>ALTER TABLE</code>.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param UniqueConstraint $constraint The unique constraint definition.
     *
     * @return string DBMS specific DDL fragment that defines the constraint.
     */
    public function getUniqueConstraintDeclarationSQL(UniqueConstraint $constraint): string
    {
        $columns = $constraint->getQuotedColumns($this);

        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        $chunks = [];

        if ($constraint->getName() !== '') {
            $chunks[] = 'CONSTRAINT';
            $chunks[] = $constraint->getQuotedName($this);
        }

        $chunks[] = 'UNIQUE';

        if ($constraint->hasFlag('clustered')) {
            $chunks[] = 'CLUSTERED';
        }

        $chunks[] = sprintf('(%s)', implode(', ', $columns));

        return implode(' ', $chunks);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param Index $index The index definition.
     *
     * @return string DBMS specific SQL code portion needed to set an index.
     */
    public function getIndexDeclarationSQL(Index $index): string
    {
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $index->getQuotedName($this)
            . ' (' . implode(', ', $index->getQuotedColumns($this)) . ')' . $this->getPartialIndexSQL($index);
    }

    /**
     * Some vendors require temporary table names to be qualified specially.
     */
    public function getTemporaryTableName(string $tableName): string
    {
        return $tableName;
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @return string DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     *                of a column declaration.
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql  = $this->getForeignKeyBaseDeclarationSQL($foreignKey);
        $sql .= $this->getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $sql;
    }

    /**
     * Returns the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key definition.
     */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';
        if ($foreignKey->hasOption('onUpdate')) {
            $query .= ' ON UPDATE ' . $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onUpdate'));
        }

        if ($foreignKey->hasOption('onDelete')) {
            $query .= ' ON DELETE ' . $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onDelete'));
        }

        return $query;
    }

    /**
     * Returns the SQL fragment representing the deferrability of a constraint.
     */
    protected function getConstraintDeferrabilitySQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql = '';

        if ($foreignKey->hasOption('deferrable')) {
            if ($foreignKey->getOption('deferrable') !== false) {
                $sql .= ' DEFERRABLE';
            } else {
                $sql .= ' NOT DEFERRABLE';
            }
        }

        if ($foreignKey->hasOption('deferred')) {
            if ($foreignKey->getOption('deferred') !== false) {
                $sql .= ' INITIALLY DEFERRED';
            } else {
                $sql .= ' INITIALLY IMMEDIATE';
            }
        }

        return $sql;
    }

    /**
     * Returns the given referential action in uppercase if valid, otherwise throws an exception.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $action The foreign key referential action.
     */
    public function getForeignKeyReferentialActionSQL(string $action): string
    {
        $upper = strtoupper($action);

        return match ($upper) {
            'CASCADE',
            'SET NULL',
            'NO ACTION',
            'RESTRICT',
            'SET DEFAULT' => $upper,
            default => throw new InvalidArgumentException(sprintf('Invalid foreign key action "%s".', $upper)),
        };
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql = '';
        if ($foreignKey->getName() !== '') {
            $sql .= 'CONSTRAINT ' . $foreignKey->getQuotedName($this) . ' ';
        }

        $sql .= 'FOREIGN KEY (';

        if (count($foreignKey->getLocalColumns()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "local" required.');
        }

        if (count($foreignKey->getForeignColumns()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "foreign" required.');
        }

        if (strlen($foreignKey->getForeignTableName()) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "foreignTable" required.');
        }

        return $sql . implode(', ', $foreignKey->getQuotedLocalColumns($this))
            . ') REFERENCES '
            . $foreignKey->getQuotedForeignTableName($this) . ' ('
            . implode(', ', $foreignKey->getQuotedForeignColumns($this)) . ')';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $charset The name of the charset.
     *
     * @return string DBMS specific SQL code portion needed to set the CHARACTER SET
     *                of a column declaration.
     */
    public function getColumnCharsetDeclarationSQL(string $charset): string
    {
        return '';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the COLLATION
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param string $collation The name of the collation.
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a column declaration.
     */
    public function getColumnCollationDeclarationSQL(string $collation): string
    {
        return $this->supportsColumnCollation() ? 'COLLATE ' . $this->quoteSingleIdentifier($collation) : '';
    }

    /**
     * Some platforms need the boolean values to be converted.
     *
     * The default conversion in this implementation converts to integers (false => 0, true => 1).
     *
     * Note: if the input is not a boolean the original input might be returned.
     *
     * There are two contexts when converting booleans: Literals and Prepared Statements.
     * This method should handle the literal case
     *
     * @param mixed $item A boolean or an array of them.
     *
     * @return mixed A boolean database value or an array of them.
     */
    public function convertBooleans(mixed $item): mixed
    {
        if (is_array($item)) {
            foreach ($item as $k => $value) {
                if (! is_bool($value)) {
                    continue;
                }

                $item[$k] = (int) $value;
            }
        } elseif (is_bool($item)) {
            $item = (int) $item;
        }

        return $item;
    }

    /**
     * Some platforms have boolean literals that needs to be correctly converted
     *
     * The default conversion tries to convert value into bool "(bool)$item"
     *
     * @param T $item
     *
     * @return (T is null ? null : bool)
     *
     * @template T
     */
    public function convertFromBoolean(mixed $item): ?bool
    {
        if ($item === null) {
            return null;
        }

        return (bool) $item;
    }

    /**
     * This method should handle the prepared statements case. When there is no
     * distinction, it's OK to use the same method.
     *
     * Note: if the input is not a boolean the original input might be returned.
     *
     * @param mixed $item A boolean or an array of them.
     *
     * @return mixed A boolean database value or an array of them.
     */
    public function convertBooleansToDatabaseValue(mixed $item): mixed
    {
        return $this->convertBooleans($item);
    }

    /**
     * Returns the SQL specific for the platform to get the current date.
     */
    public function getCurrentDateSQL(): string
    {
        return 'CURRENT_DATE';
    }

    /**
     * Returns the SQL specific for the platform to get the current time.
     */
    public function getCurrentTimeSQL(): string
    {
        return 'CURRENT_TIME';
    }

    /**
     * Returns the SQL specific for the platform to get the current timestamp
     */
    public function getCurrentTimestampSQL(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    /**
     * Returns the SQL for a given transaction isolation level Connection constant.
     */
    protected function _getTransactionIsolationLevelSQL(TransactionIsolationLevel $level): string
    {
        return match ($level) {
            TransactionIsolationLevel::READ_UNCOMMITTED => 'READ UNCOMMITTED',
            TransactionIsolationLevel::READ_COMMITTED => 'READ COMMITTED',
            TransactionIsolationLevel::REPEATABLE_READ => 'REPEATABLE READ',
            TransactionIsolationLevel::SERIALIZABLE => 'SERIALIZABLE',
        };
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListDatabasesSQL(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListSequencesSQL(string $database): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to list all views of a database or user.
     *
     * @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy.
     */
    abstract public function getListViewsSQL(string $database): string;

    public function getCreateViewSQL(string $name, string $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    public function getDropViewSQL(string $name): string
    {
        return 'DROP VIEW ' . $name;
    }

    public function getSequenceNextValSQL(string $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to create a new database.
     *
     * @param string $name The name of the database that should be created.
     */
    public function getCreateDatabaseSQL(string $name): string
    {
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * Returns the SQL snippet to drop an existing database.
     *
     * @param string $name The name of the database that should be dropped.
     */
    public function getDropDatabaseSQL(string $name): string
    {
        return 'DROP DATABASE ' . $name;
    }

    /**
     * Returns the SQL to set the transaction isolation level.
     */
    abstract public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string;

    /**
     * Obtains DBMS specific SQL to be used to create datetime columns in
     * statements like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getDateTimeTypeDeclarationSQL(array $column): string;

    /**
     * Obtains DBMS specific SQL to be used to create datetime with timezone offset columns.
     *
     * @param array<string, mixed> $column
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return $this->getDateTimeTypeDeclarationSQL($column);
    }

    /**
     * Obtains DBMS specific SQL to be used to create date columns in statements
     * like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getDateTypeDeclarationSQL(array $column): string;

    /**
     * Obtains DBMS specific SQL to be used to create time columns in statements
     * like CREATE TABLE.
     *
     * @param array<string, mixed> $column
     */
    abstract public function getTimeTypeDeclarationSQL(array $column): string;

    /** @param array<string, mixed> $column */
    public function getFloatDeclarationSQL(array $column): string
    {
        return 'DOUBLE PRECISION';
    }

    /** @param array<string, mixed> $column */
    public function getSmallFloatDeclarationSQL(array $column): string
    {
        return 'REAL';
    }

    /**
     * Gets the default transaction isolation level of the platform.
     *
     * @return TransactionIsolationLevel The default isolation level.
     */
    public function getDefaultTransactionIsolationLevel(): TransactionIsolationLevel
    {
        return TransactionIsolationLevel::READ_COMMITTED;
    }

    /* supports*() methods */

    /**
     * Whether the platform supports sequences.
     */
    public function supportsSequences(): bool
    {
        return false;
    }

    /**
     * Whether the platform supports identity columns.
     *
     * Identity columns are columns that receive an auto-generated value from the
     * database on insert of a row.
     */
    public function supportsIdentityColumns(): bool
    {
        return false;
    }

    /**
     * Whether the platform supports partial indexes.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supportsPartialIndexes(): bool
    {
        return false;
    }

    /**
     * Whether the platform supports indexes with column length definitions.
     *
     * @deprecated
     */
    public function supportsColumnLengthIndexes(): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated.',
            __METHOD__,
        );

        return false;
    }

    /**
     * Whether the platform supports savepoints.
     */
    public function supportsSavepoints(): bool
    {
        return true;
    }

    /**
     * Whether the platform supports releasing savepoints.
     */
    public function supportsReleaseSavepoints(): bool
    {
        return $this->supportsSavepoints();
    }

    /**
     * Whether the platform supports database schemas.
     */
    public function supportsSchemas(): bool
    {
        return false;
    }

    /**
     * Whether this platform support to add inline column comments as postfix.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supportsInlineColumnComments(): bool
    {
        return false;
    }

    /**
     * Whether this platform support the proprietary syntax "COMMENT ON asset".
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supportsCommentOnStatement(): bool
    {
        return false;
    }

    /**
     * Does this platform support column collation?
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function supportsColumnCollation(): bool
    {
        return false;
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime value of this platform.
     *
     * @return string The format string.
     */
    public function getDateTimeFormatString(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime with timezone value of this platform.
     *
     * @return string The format string.
     */
    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored date value of this platform.
     *
     * @return string The format string.
     */
    public function getDateFormatString(): string
    {
        return 'Y-m-d';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored time value of this platform.
     *
     * @return string The format string.
     */
    public function getTimeFormatString(): string
    {
        return 'H:i:s';
    }

    /**
     * Adds an driver-specific LIMIT clause to the query.
     */
    final public function modifyLimitQuery(string $query, ?int $limit, int $offset = 0): string
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf(
                'Offset must be a positive integer or zero, %d given.',
                $offset,
            ));
        }

        return $this->doModifyLimitQuery($query, $limit, $offset);
    }

    /**
     * Adds an platform-specific LIMIT clause to the query.
     */
    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);
        }

        if ($offset > 0) {
            $query .= sprintf(' OFFSET %d', $offset);
        }

        return $query;
    }

    /**
     * Maximum length of any given database identifier, like tables or column names.
     *
     * @return positive-int
     */
    public function getMaxIdentifierLength(): int
    {
        return 63;
    }

    /**
     * Returns the insert SQL for an empty insert statement.
     */
    public function getEmptyIdentityInsertSQL(string $quotedTableName, string $quotedIdentifierColumnName): string
    {
        return 'INSERT INTO ' . $quotedTableName . ' (' . $quotedIdentifierColumnName . ') VALUES (null)';
    }

    /**
     * Generates a Truncate Table SQL statement for a given table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign keys.
     */
    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE ' . $tableIdentifier->getQuotedName($this);
    }

    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     */
    public function getDummySelectSQL(string $expression = '1'): string
    {
        return sprintf('SELECT %s', $expression);
    }

    /**
     * Returns the SQL to create a new savepoint.
     */
    public function createSavePoint(string $savepoint): string
    {
        return 'SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to release a savepoint.
     */
    public function releaseSavePoint(string $savepoint): string
    {
        return 'RELEASE SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to rollback a savepoint.
     */
    public function rollbackSavePoint(string $savepoint): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the keyword list instance of this platform.
     *
     * @deprecated
     */
    final public function getReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

        // Store the instance so it doesn't need to be generated on every request.
        return $this->_keywords ??= $this->createReservedKeywordsList();
    }

    /**
     * Creates an instance of the reserved keyword list of this platform.
     *
     * @deprecated
     */
    abstract protected function createReservedKeywordsList(): KeywordList;

    /**
     * Quotes a literal string.
     * This method is NOT meant to fix SQL injections!
     * It is only meant to escape this platform's string literal
     * quote character inside the given literal string.
     *
     * @param string $str The literal string to be quoted.
     *
     * @return string The quoted literal string.
     */
    public function quoteStringLiteral(string $str): string
    {
        return "'" . str_replace("'", "''", $str) . "'";
    }

    /**
     * Escapes metacharacters in a string intended to be used with a LIKE
     * operator.
     *
     * @param string $inputString a literal, unquoted string
     * @param string $escapeChar  should be reused by the caller in the LIKE
     *                            expression.
     */
    final public function escapeStringForLike(string $inputString, string $escapeChar): string
    {
        $sql = preg_replace(
            '~([' . preg_quote($this->getLikeWildcardCharacters() . $escapeChar, '~') . '])~u',
            addcslashes($escapeChar, '\\') . '$1',
            $inputString,
        );

        assert(is_string($sql));

        return $sql;
    }

    /**
     * @return ColumnProperties An associative array with the name of the properties of the column being declared as
     *                          array keys.
     */
    private function columnToArray(Column $column): array
    {
        return array_merge($column->toArray(), [
            'name' => $column->getQuotedName($this),
            'version' => $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false,
            'comment' => $column->getComment(),
        ]);
    }

    /** @internal */
    public function createSQLParser(): Parser
    {
        return new Parser(false);
    }

    protected function getLikeWildcardCharacters(): string
    {
        return '%_';
    }

    /**
     * Compares the definitions of the given columns in the context of this platform.
     */
    public function columnsEqual(Column $column1, Column $column2): bool
    {
        $column1Array = $this->columnToArray($column1);
        $column2Array = $this->columnToArray($column2);

        // ignore explicit columnDefinition since it's not set on the Column generated by the SchemaManager
        $column1Array['columnDefinition'] = null;
        $column2Array['columnDefinition'] = null;

        if (
            $this->getColumnDeclarationSQL('', $column1Array)
            !== $this->getColumnDeclarationSQL('', $column2Array)
        ) {
            return false;
        }

        // If the platform supports inline comments, all comparison is already done above
        if ($this->supportsInlineColumnComments()) {
            return true;
        }

        return $column1->getComment() === $column2->getComment();
    }

    /**
     * Returns the union select query part surrounded by parenthesis if possible for platform.
     */
    public function getUnionSelectPartSQL(string $subQuery): string
    {
        return sprintf('(%s)', $subQuery);
    }

    /**
     * Returns the `UNION ALL` keyword.
     */
    public function getUnionAllSQL(): string
    {
        return 'UNION ALL';
    }

    /**
     * Returns the compatible `UNION DISTINCT` keyword.
     */
    public function getUnionDistinctSQL(): string
    {
        return 'UNION';
    }

    public function getUnquotedIdentifierFolding(): UnquotedIdentifierFolding
    {
        if ($this->unquotedIdentifierFolding === null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6823',
                'Not calling the %s constructor from child class constructors is deprecated.',
                self::class,
            );

            $this->unquotedIdentifierFolding = UnquotedIdentifierFolding::UPPER;
        }

        return $this->unquotedIdentifierFolding;
    }

    /**
     * Creates a metadata provider that can be used access the metadata of the underlying database schema.
     *
     * @throws Exception
     */
    public function createMetadataProvider(Connection $connection): MetadataProvider
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Creates the schema manager that can be used to inspect and change the underlying
     * database schema according to the dialect of the platform.
     */
    abstract public function createSchemaManager(Connection $connection): AbstractSchemaManager;
}

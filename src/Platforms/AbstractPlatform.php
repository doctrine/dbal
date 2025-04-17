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
use Doctrine\DBAL\Platforms\Exception\UnsupportedIndexDefinition;
use Doctrine\DBAL\Platforms\Exception\UnsupportedPrimaryKeyConstraintDefinition;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
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

use function addcslashes;
use function array_map;
use function array_merge;
use function assert;
use function count;
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
use function str_replace;
use function strtolower;

/**
 * Base class for all DatabasePlatforms. The DatabasePlatforms are the central
 * point of abstraction of platform-specific behaviors, features and SQL dialects.
 * They are a passive source of information.
 *
 * @phpstan-import-type ColumnProperties from Column
 * @phpstan-type CreateTableParameters = array{
 *    indexes: list<Index>,
 *    primaryKey: ?PrimaryKeyConstraint,
 *    uniqueConstraints: list<UniqueConstraint>,
 *    foreignKeys: list<ForeignKeyConstraint>,
 *    comment?: string,
 * }
 */
abstract class AbstractPlatform
{
    /** @var string[]|null */
    protected ?array $doctrineTypeMapping = null;

    /**
     * Defines how the platform folds the case of unquoted identifiers.
     */
    private readonly UnquotedIdentifierFolding $unquotedIdentifierFolding;

    protected function __construct(UnquotedIdentifierFolding $unquotedIdentifierFolding)
    {
        $this->unquotedIdentifierFolding = $unquotedIdentifierFolding;
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
        return $this->getClobTypeDeclarationSQL($column);
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

    /** @return list<string> */
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

        $tableName                       = $table->getObjectName();
        $parameters                      = $table->getOptions();
        $parameters['primaryKey']        = $table->getPrimaryKeyConstraint();
        $parameters['indexes']           = [];
        $parameters['uniqueConstraints'] = [];
        $parameters['foreignKeys']       = [];

        foreach ($table->getIndexes() as $index) {
            $parameters['indexes'][] = $index;
        }

        foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
            $parameters['uniqueConstraints'][] = $uniqueConstraint;
        }

        if ($createForeignKeys) {
            foreach ($table->getForeignKeys() as $fkConstraint) {
                $parameters['foreignKeys'][] = $fkConstraint;
            }
        }

        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columns[] = $this->columnToArray($column);
        }

        $sql = $this->_getCreateTableSQL($tableName, $columns, $parameters);

        if ($this->supportsCommentOnStatement()) {
            if ($table->hasOption('comment')) {
                $sql[] = $this->getCommentOnTableSQL($tableName->toSQL($this), $table->getOption('comment'));
            }

            foreach ($table->getColumns() as $column) {
                $comment = $column->getComment();

                if ($comment === '') {
                    continue;
                }

                $sql[] = $this->getCommentOnColumnSQL(
                    $tableName->toSQL($this),
                    $column->getObjectName()->toSQL($this),
                    $comment,
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
                    $table->getObjectName()->toSQL($this),
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
                    $this->getConstraintName($foreignKey)->toSQL($this),
                    $table->getObjectName()->toSQL($this),
                );
            }
        }

        foreach ($tables as $table) {
            $sql[] = $this->getDropTableSQL($table->getObjectName()->toSQL($this));
        }

        return $sql;
    }

    protected function getCommentOnTableSQL(string $tableName, string $comment): string
    {
        $tableName = new Identifier($tableName);

        return sprintf(
            'COMMENT ON TABLE %s IS %s',
            $tableName->getObjectName()->toSQL($this),
            $this->quoteStringLiteral($comment),
        );
    }

    protected function getCommentOnColumnSQL(string $tableName, string $columnName, string $comment): string
    {
        $tableName  = new Identifier($tableName);
        $columnName = new Identifier($columnName);

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s',
            $tableName->getObjectName()->toSQL($this),
            $columnName->getObjectName()->toSQL($this),
            $this->quoteStringLiteral($comment),
        );
    }

    /**
     * Returns the SQL to create inline comment on a column.
     */
    protected function getInlineColumnCommentSQL(string $comment): string
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
     * @param CreateTableParameters  $parameters
     *
     * @return list<string>
     */
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        $elements = [];

        foreach ($columns as $column) {
            $elements[] = $this->getColumnDeclarationSQL($column);
        }

        foreach ($parameters['uniqueConstraints'] as $definition) {
            $elements[] = $this->getUniqueConstraintDeclarationSQL($definition);
        }

        if (isset($parameters['primaryKey'])) {
            $elements[] = $this->getPrimaryKeyConstraintDeclarationSQL($parameters['primaryKey']);
        }

        $elements = array_merge($elements, $this->getCheckDeclarationSQL($columns));

        $query = 'CREATE TABLE ' . $tableName->toSQL($this) . ' (' . implode(', ', $elements) . ')';

        $sql = [$query];

        foreach ($parameters['foreignKeys'] as $definition) {
            $sql[] = $this->getCreateForeignKeySQL($definition, $tableName->toSQL($this));
        }

        return $sql;
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
                $sql[] = $this->getDropSequenceSQL($sequence->getObjectName()->toSQL($this));
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
        $chunks = ['CREATE'];
        $type   = $index->getType();

        if ($type === IndexType::UNIQUE) {
            $chunks[] = 'UNIQUE';
        } elseif ($type === IndexType::FULLTEXT) {
            $chunks[] = 'FULLTEXT';
        } elseif ($type === IndexType::SPATIAL) {
            $chunks[] = 'SPATIAL';
        }

        if ($index->isClustered()) {
            $chunks[] = 'CLUSTERED';
        }

        $chunks[] = 'INDEX';
        $chunks[] = $index->getObjectName()->toSQL($this);
        $chunks[] = 'ON';
        $chunks[] = $table;
        $chunks[] = $this->buildIndexedColumnListSQL($index->getIndexedColumns());

        $predicate = $index->getPredicate();
        if ($predicate !== null) {
            $chunks[] = 'WHERE';
            $chunks[] = $predicate;
        }

        return implode(' ', $chunks);
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
     * Quotes a string so that it can be safely used as an identifier in SQL.
     */
    public function quoteSingleIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
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
        $tableNameSQL = $diff->getOldTable()->getObjectName()->toSQL($this);

        $sql = [];

        foreach ($diff->getDroppedForeignKeys() as $foreignKey) {
            $sql[] = $this->getDropForeignKeySQL($this->getConstraintName($foreignKey)->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getDroppedIndexes() as $index) {
            $sql[] = $this->getDropIndexSQL($index->getObjectName()->toSQL($this), $tableNameSQL);
        }

        return $sql;
    }

    /** @return list<string> */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql = [];

        $tableNameSQL = $diff->getOldTable()->getObjectName()->toSQL($this);

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getRenamedIndexes() as $oldIndexName => $index) {
            $oldIndexName = new Identifier($oldIndexName);
            $sql          = array_merge(
                $sql,
                $this->getRenameIndexSQL($oldIndexName->getObjectName()->toSQL($this), $index, $tableNameSQL),
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
            $declarations[] = $this->getColumnDeclarationSQL($column);
        }

        return implode(', ', $declarations);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to declare a generic type
     * column to be used in statements like CREATE TABLE.
     *
     * @param ColumnProperties $column Column properties.
     *
     * @return string DBMS specific SQL code portion that should be used to declare the column.
     */
    protected function getColumnDeclarationSQL(array $column): string
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

        return $column['name']->toSQL($this) . ' ' . $declaration;
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
     * @param array<string, mixed> $column The column definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    protected function getDefaultValueDeclarationSQL(array $column): string
    {
        if (! isset($column['default'])) {
            return empty($column['notnull']) ? ' DEFAULT NULL' : '';
        }

        $default = $column['default'];

        if (! isset($column['type'])) {
            return " DEFAULT '" . $default . "'";
        }

        $type = $column['type'];

        if ($type instanceof Types\PhpIntegerMappingType) {
            return ' DEFAULT ' . $default;
        }

        if ($type instanceof Types\PhpDateTimeMappingType && $default === $this->getCurrentTimestampSQL()) {
            return ' DEFAULT ' . $this->getCurrentTimestampSQL();
        }

        if ($type instanceof Types\PhpTimeMappingType && $default === $this->getCurrentTimeSQL()) {
            return ' DEFAULT ' . $this->getCurrentTimeSQL();
        }

        if ($type instanceof Types\PhpDateMappingType && $default === $this->getCurrentDateSQL()) {
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
     * @param list<ColumnProperties> $column The column properties.
     *
     * @return list<string> A list of SQL expressions each representing an individual CHECK constraint.
     */
    protected function getCheckDeclarationSQL(array $column): array
    {
        $constraints = [];
        foreach ($column as $def) {
            $name = $def['name']->toSQL($this);

            if (isset($def['min'])) {
                $constraints[] = 'CHECK (' . $name . ' >= ' . $def['min'] . ')';
            }

            if (! isset($def['max'])) {
                continue;
            }

            $constraints[] = 'CHECK (' . $name . ' <= ' . $def['max'] . ')';
        }

        return $constraints;
    }

    /**
     * Obtains DBMS specific DDL fragment that defines a unique constraint to be used in statements like <code>CREATE
     * TABLE</code> or <code>ALTER TABLE</code>.
     *
     * @param UniqueConstraint $constraint The unique constraint definition.
     *
     * @return string DBMS specific DDL fragment that defines the constraint.
     */
    protected function getUniqueConstraintDeclarationSQL(UniqueConstraint $constraint): string
    {
        $chunks = [];

        $name = $constraint->getObjectName();
        if ($name !== null) {
            $chunks[] = 'CONSTRAINT';
            $chunks[] = $name->toSQL($this);
        }

        $chunks[] = 'UNIQUE';

        if ($constraint->isClustered()) {
            $chunks[] = 'CLUSTERED';
        }

        $chunks[] = $this->buildUnqualifiedNameListSQL($constraint->getColumnNames());

        return implode(' ', $chunks);
    }

    /**
     * Some vendors require temporary table names to be qualified specially.
     */
    public function getTemporaryTableName(string $tableName): string
    {
        return $tableName;
    }

    /**
     * Returns declaration of a primary key constraint.
     */
    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $chunks = [];

        $name = $constraint->getObjectName();
        if ($name !== null) {
            $chunks[] = 'CONSTRAINT';
            $chunks[] = $name->toSQL($this);
        }

        $chunks[] = 'PRIMARY KEY';

        if (! $constraint->isClustered()) {
            $chunks[] = 'NONCLUSTERED';
        }

        $chunks[] = $this->buildUnqualifiedNameListSQL($constraint->getColumnNames());

        return implode(' ', $chunks);
    }

    final protected function ensurePrimaryKeyConstraintIsNotNamed(PrimaryKeyConstraint $constraint): void
    {
        if ($constraint->getObjectName() === null) {
            return;
        }

        throw UnsupportedPrimaryKeyConstraintDefinition::fromNamedConstraint(static::class);
    }

    final protected function ensurePrimaryKeyConstraintIsClustered(PrimaryKeyConstraint $constraint): void
    {
        if ($constraint->isClustered()) {
            return;
        }

        throw UnsupportedPrimaryKeyConstraintDefinition::fromNonClusteredConstraint(static::class);
    }

    final protected function ensureIndexHasNoColumnLengths(Index $index): void
    {
        foreach ($index->getIndexedColumns() as $column) {
            if ($column->getLength() !== null) {
                throw UnsupportedIndexDefinition::fromIndexWithColumnLengths(static::class);
            }
        }
    }

    final protected function ensureIndexIsNotFulltext(Index $index): void
    {
        if ($index->getType() !== IndexType::FULLTEXT) {
            return;
        }

        throw UnsupportedIndexDefinition::fromFulltextIndex(static::class);
    }

    final protected function ensureIndexIsNotSpatial(Index $index): void
    {
        if ($index->getType() !== IndexType::SPATIAL) {
            return;
        }

        throw UnsupportedIndexDefinition::fromSpatialIndex(static::class);
    }

    final protected function ensureIndexIsNotClustered(Index $index): void
    {
        if (! $index->isClustered()) {
            return;
        }

        throw UnsupportedIndexDefinition::fromClusteredIndex(static::class);
    }

    final protected function ensureIndexIsNotPartial(Index $index): void
    {
        if ($index->getPredicate() === null) {
            return;
        }

        throw UnsupportedIndexDefinition::fromPartialIndex(static::class);
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @return string DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     *                of a column declaration.
     */
    protected function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql  = $this->getForeignKeyBaseDeclarationSQL($foreignKey);
        $sql .= $this->getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $sql;
    }

    /**
     * Returns the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key definition.
     */
    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $query = '';

        $onUpdateAction = $foreignKey->getOnUpdateAction();
        if ($onUpdateAction !== ReferentialAction::NO_ACTION) {
            $query .= ' ON UPDATE ' . $this->getForeignKeyReferentialActionSQL($onUpdateAction);
        }

        $onDeleteAction = $foreignKey->getOnDeleteAction();
        if ($onDeleteAction !== ReferentialAction::NO_ACTION) {
            $query .= ' ON DELETE ' . $this->getForeignKeyReferentialActionSQL($onDeleteAction);
        }

        return $query;
    }

    /**
     * Returns the given referential action in uppercase if valid, otherwise throws an exception.
     *
     * @param ReferentialAction $action The foreign key referential action.
     */
    protected function getForeignKeyReferentialActionSQL(ReferentialAction $action): string
    {
        return $action->toSQL();
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     */
    protected function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $chunks = [];

        $name = $foreignKey->getObjectName();
        if ($name !== null) {
            $chunks[] = 'CONSTRAINT';
            $chunks[] = $name->toSQL($this);
        }

        $chunks[] = 'FOREIGN KEY';
        $chunks[] = $this->buildUnqualifiedNameListSQL($foreignKey->getReferencingColumnNames());
        $chunks[] = 'REFERENCES';
        $chunks[] = $foreignKey->getReferencedTableName()->toSQL($this);
        $chunks[] = $this->buildUnqualifiedNameListSQL($foreignKey->getReferencedColumnNames());

        return implode(' ', $chunks);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset The name of the charset.
     *
     * @return string DBMS specific SQL code portion needed to set the CHARACTER SET
     *                of a column declaration.
     */
    protected function getColumnCharsetDeclarationSQL(string $charset): string
    {
        return '';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the COLLATION
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @param string $collation The name of the collation.
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a column declaration.
     */
    protected function getColumnCollationDeclarationSQL(string $collation): string
    {
        return $this->supportsColumnCollation() ? 'COLLATE ' . $this->quoteSingleIdentifier($collation) : '';
    }

    /** @param non-empty-list<UnqualifiedName> $names */
    private function buildUnqualifiedNameListSQL(array $names): string
    {
        return sprintf('(%s)', implode(', ', array_map(
            fn (UnqualifiedName $columnName) => $columnName->toSQL($this),
            $names,
        )));
    }

    /** @param non-empty-list<IndexedColumn> $indexedColumns */
    protected function buildIndexedColumnListSQL(array $indexedColumns): string
    {
        return sprintf('(%s)', implode(', ', array_map(
            function (IndexedColumn $indexedColumn): string {
                $sql = $indexedColumn->getColumnName()->toSQL($this);

                $length = $indexedColumn->getLength();
                if ($length !== null) {
                    $sql .= sprintf('(%s)', $length);
                }

                return $sql;
            },
            $indexedColumns,
        )));
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
     */
    protected function supportsInlineColumnComments(): bool
    {
        return false;
    }

    /**
     * Whether this platform support the proprietary syntax "COMMENT ON asset".
     */
    protected function supportsCommentOnStatement(): bool
    {
        return false;
    }

    /**
     * Does this platform support column collation?
     */
    protected function supportsColumnCollation(): bool
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

        return 'TRUNCATE ' . $tableIdentifier->getObjectName()->toSQL($this);
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
            'version' => $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false,
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

        $name = UnqualifiedName::unquoted('dummy');

        // ignore the difference in column names to enable detection of renamed columns
        $column1Array['name'] = $name;
        $column2Array['name'] = $name;

        // ignore explicit columnDefinition since it's not set on the Column generated by the SchemaManager
        $column1Array['columnDefinition'] = null;
        $column2Array['columnDefinition'] = null;

        if (
            $this->getColumnDeclarationSQL($column1Array)
            !== $this->getColumnDeclarationSQL($column2Array)
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
        return $this->unquotedIdentifierFolding;
    }

    /**
     * Creates the schema manager that can be used to inspect and change the underlying
     * database schema according to the dialect of the platform.
     */
    abstract public function createSchemaManager(Connection $connection): AbstractSchemaManager;

    private function getConstraintName(ForeignKeyConstraint $constraint): UnqualifiedName
    {
        $name = $constraint->getObjectName();

        if ($name === null) {
            throw UnspecifiedConstraintName::new();
        }

        return $name;
    }
}

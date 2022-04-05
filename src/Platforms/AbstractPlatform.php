<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Event\SchemaDropTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ColumnLengthRequired;
use Doctrine\DBAL\Exception\InvalidLockMode;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\Exception\NoColumnsSpecifiedForTable;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use Doctrine\DBAL\Types\Exception\TypeNotFound;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use UnexpectedValueException;

use function addcslashes;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
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
 * @todo Remove any unnecessary methods.
 */
abstract class AbstractPlatform
{
    public const CREATE_INDEXES = 1;

    public const CREATE_FOREIGNKEYS = 2;

    /** @var string[]|null */
    protected ?array $doctrineTypeMapping = null;

    protected ?EventManager $_eventManager = null;

    /**
     * Holds the KeywordList instance for the current platform.
     */
    protected ?KeywordList $_keywords = null;

    /**
     * Sets the EventManager used by the Platform.
     */
    public function setEventManager(EventManager $eventManager): void
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * Gets the EventManager used by the Platform.
     */
    public function getEventManager(): ?EventManager
    {
        return $this->_eventManager;
    }

    /**
     * Returns the SQL snippet that declares a boolean column.
     *
     * @param mixed[] $column
     */
    abstract public function getBooleanTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares a 4 byte integer column.
     *
     * @param mixed[] $column
     */
    abstract public function getIntegerTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares an 8 byte integer column.
     *
     * @param mixed[] $column
     */
    abstract public function getBigIntTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares a 2 byte integer column.
     *
     * @param mixed[] $column
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL snippet that declares common properties of an integer column.
     *
     * @param mixed[] $column
     */
    abstract protected function _getCommonIntegerTypeDeclarationSQL(array $column): string;

    /**
     * Lazy load Doctrine Type Mappings.
     */
    abstract protected function initializeDoctrineTypeMappings(): void;

    /**
     * Initializes Doctrine Type Mappings with the platform defaults
     * and with all additional type mappings.
     */
    private function initializeAllDoctrineTypeMappings(): void
    {
        $this->initializeDoctrineTypeMappings();

        foreach (Type::getTypesMap() as $typeName => $className) {
            foreach (Type::getType($typeName)->getMappedDatabaseTypes($this) as $dbType) {
                $this->doctrineTypeMapping[$dbType] = $typeName;
            }
        }
    }

    /**
     * Returns the SQL snippet used to declare a column that can
     * store characters in the ASCII character set
     *
     * @param array<string, mixed> $column The column definition.
     *
     * @throws ColumnLengthRequired
     */
    public function getAsciiStringTypeDeclarationSQL(array $column): string
    {
        return $this->getStringTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet used to declare a string column type.
     *
     * @param array<string, mixed> $column The column definition.
     *
     * @throws ColumnLengthRequired
     */
    public function getStringTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        if (empty($column['fixed'])) {
            return $this->getVarcharTypeDeclarationSQLSnippet($length);
        }

        return $this->getCharTypeDeclarationSQLSnippet($length);
    }

    /**
     * Returns the SQL snippet used to declare a binary string column type.
     *
     * @param array<string, mixed> $column The column definition.
     *
     * @throws ColumnLengthRequired
     */
    public function getBinaryTypeDeclarationSQL(array $column): string
    {
        $length = $column['length'] ?? null;

        if (empty($column['fixed'])) {
            return $this->getVarbinaryTypeDeclarationSQLSnippet($length);
        }

        return $this->getBinaryTypeDeclarationSQLSnippet($length);
    }

    /**
     * Returns the SQL snippet to declare a GUID/UUID column.
     *
     * By default this maps directly to a CHAR(36) and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array<string, mixed> $column The column definition.
     *
     * @throws Exception
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
     * @param mixed[] $column
     */
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        return $this->getClobTypeDeclarationSQL($column);
    }

    /**
     * @param int|null $length The length of the column in characters
     *                         or NULL if the length should be omitted.
     *
     * @throws ColumnLengthRequired
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
     *
     * @throws ColumnLengthRequired
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
     *
     * @throws ColumnLengthRequired
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
     *
     * @throws ColumnLengthRequired
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
     * @param mixed[] $column
     */
    abstract public function getClobTypeDeclarationSQL(array $column): string;

    /**
     * Returns the SQL Snippet used to declare a BLOB column type.
     *
     * @param mixed[] $column
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
     * @throws Exception
     */
    public function getDoctrineTypeMapping(string $dbType): string
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if (! isset($this->doctrineTypeMapping[$dbType])) {
            throw new Exception(sprintf(
                'Unknown database type "%s" requested, %s may not support it.',
                $dbType,
                static::class
            ));
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Checks if a database type is currently supported by this platform.
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
     * Gets the character used for identifier quoting.
     */
    public function getIdentifierQuoteCharacter(): string
    {
        return '"';
    }

    /**
     * Returns the regular expression operator.
     *
     * @throws Exception If not supported on this platform.
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
     * @param int         $mode The position of the trim (leading/trailing/both).
     * @param string|null $char The char to trim, has to be quoted already. Defaults to space.
     *
     * @throws InvalidArgumentException
     */
    public function getTrimExpression(string $str, int $mode = TrimMode::UNSPECIFIED, ?string $char = null): string
    {
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

            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'The value of $mode is expected to be one of the TrimMode constants, %d given.',
                        $mode
                    )
                );
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
     * @param string $date     SQL expression representing a date to perform the arithmetic operation on.
     * @param string $operator The arithmetic operator (+ or -).
     * @param string $interval SQL expression representing the value of the interval that shall be calculated
     *                         into the date.
     * @param string $unit     The unit of the interval that shall be calculated into the date.
     *                         One of the DATE_INTERVAL_UNIT_* constants.
     */
    abstract protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        string $unit
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
     * Returns the FOR UPDATE expression.
     */
    public function getForUpdateSQL(): string
    {
        return 'FOR UPDATE';
    }

    /**
     * Honors that some SQL vendors such as MsSql use table hints for locking instead of the
     * ANSI SQL FOR UPDATE specification.
     *
     * @param string $fromClause The FROM clause to append the hint for the given lock mode to
     * @param int    $lockMode   One of the Doctrine\DBAL\LockMode::* constants
     * @psalm-param LockMode::* $lockMode
     */
    public function appendLockHint(string $fromClause, int $lockMode): string
    {
        switch ($lockMode) {
            case LockMode::NONE:
            case LockMode::OPTIMISTIC:
            case LockMode::PESSIMISTIC_READ:
            case LockMode::PESSIMISTIC_WRITE:
                return $fromClause;

            default:
                throw InvalidLockMode::fromLockMode($lockMode);
        }
    }

    /**
     * Returns the SQL snippet to append to any SELECT statement which locks rows in shared read lock.
     *
     * This defaults to the ANSI SQL "FOR UPDATE", which is an exclusive lock (Write). Some database
     * vendors allow to lighten this constraint up to be a real read lock.
     */
    public function getReadLockSQL(): string
    {
        return $this->getForUpdateSQL();
    }

    /**
     * Returns the SQL snippet to append to any SELECT statement which obtains an exclusive lock on the rows.
     *
     * The semantics of this lock mode should equal the SELECT .. FOR UPDATE of the ANSI SQL standard.
     */
    public function getWriteLockSQL(): string
    {
        return $this->getForUpdateSQL();
    }

    /**
     * Returns the SQL snippet to drop an existing table.
     */
    public function getDropTableSQL(string $table): string
    {
        if ($this->_eventManager !== null && $this->_eventManager->hasListeners(Events::onSchemaDropTable)) {
            $eventArgs = new SchemaDropTableEventArgs($table, $this);
            $this->_eventManager->dispatchEvent(Events::onSchemaDropTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                $sql = $eventArgs->getSql();

                if ($sql === null) {
                    throw new UnexpectedValueException(
                        'Default implementation of DROP TABLE was overridden with NULL.'
                    );
                }

                return $sql;
            }
        }

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
     * @psalm-param int-mask-of<self::CREATE_*> $createFlags
     *
     * @return array<int, string> The sequence of SQL statements.
     *
     * @throws Exception
     */
    public function getCreateTableSQL(Table $table, int $createFlags = self::CREATE_INDEXES): array
    {
        if (count($table->getColumns()) === 0) {
            throw NoColumnsSpecifiedForTable::new($table->getName());
        }

        $tableName                    = $table->getQuotedName($this);
        $options                      = $table->getOptions();
        $options['uniqueConstraints'] = [];
        $options['indexes']           = [];
        $options['primary']           = [];

        if (($createFlags & self::CREATE_INDEXES) > 0) {
            foreach ($table->getIndexes() as $index) {
                if (! $index->isPrimary()) {
                    $options['indexes'][$index->getQuotedName($this)] = $index;

                    continue;
                }

                $options['primary']       = $index->getQuotedColumns($this);
                $options['primary_index'] = $index;
            }

            foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
                $options['uniqueConstraints'][$uniqueConstraint->getQuotedName($this)] = $uniqueConstraint;
            }
        }

        if (($createFlags & self::CREATE_FOREIGNKEYS) > 0) {
            $options['foreignKeys'] = [];

            foreach ($table->getForeignKeys() as $fkConstraint) {
                $options['foreignKeys'][] = $fkConstraint;
            }
        }

        $columnSql = [];
        $columns   = [];

        foreach ($table->getColumns() as $column) {
            if (
                $this->_eventManager !== null
                && $this->_eventManager->hasListeners(Events::onSchemaCreateTableColumn)
            ) {
                $eventArgs = new SchemaCreateTableColumnEventArgs($column, $table, $this);

                $this->_eventManager->dispatchEvent(Events::onSchemaCreateTableColumn, $eventArgs);

                $columnSql = array_merge($columnSql, $eventArgs->getSql());

                if ($eventArgs->isDefaultPrevented()) {
                    continue;
                }
            }

            $columnData = $this->columnToArray($column);

            if (in_array($column->getName(), $options['primary'], true)) {
                $columnData['primary'] = true;
            }

            $columns[] = $columnData;
        }

        if ($this->_eventManager !== null && $this->_eventManager->hasListeners(Events::onSchemaCreateTable)) {
            $eventArgs = new SchemaCreateTableEventArgs($table, $columns, $options, $this);

            $this->_eventManager->dispatchEvent(Events::onSchemaCreateTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                return array_merge($eventArgs->getSql(), $columnSql);
            }
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

        return array_merge($sql, $columnSql);
    }

    protected function getCommentOnTableSQL(string $tableName, string $comment): string
    {
        $tableName = new Identifier($tableName);

        return sprintf(
            'COMMENT ON TABLE %s IS %s',
            $tableName->getQuotedName($this),
            $this->quoteStringLiteral($comment)
        );
    }

    public function getCommentOnColumnSQL(string $tableName, string $columnName, string $comment): string
    {
        $tableName  = new Identifier($tableName);
        $columnName = new Identifier($columnName);

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s',
            $tableName->getQuotedName($this),
            $columnName->getQuotedName($this),
            $this->quoteStringLiteral($comment)
        );
    }

    /**
     * Returns the SQL to create inline comment on a column.
     *
     * @throws Exception If not supported on this platform.
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
     * @param mixed[][] $columns
     * @param mixed[]   $options
     *
     * @return array<int, string>
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY(' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
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
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $name);
            }
        }

        return $sql;
    }

    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE TEMPORARY TABLE';
    }

    /**
     * Returns the SQL to create a sequence on this platform.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to change a sequence on this platform.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to drop an existing sequence.
     *
     * @throws Exception If not supported on this platform.
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
     *
     * @throws InvalidArgumentException
     */
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $name    = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        $query  = 'CREATE ' . $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ON ' . $table;
        $query .= ' (' . $this->getIndexFieldDeclarationListSQL($index) . ')' . $this->getPartialIndexSQL($index);

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
     */
    public function getCreatePrimaryKeySQL(Index $index, string $table): string
    {
        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . $this->getIndexFieldDeclarationListSQL($index) . ')';
    }

    /**
     * Returns the SQL to create a named schema.
     *
     * @throws Exception If not supported on this platform.
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
        return 'ALTER TABLE ' . $tableName . ' ADD CONSTRAINT ' . $constraint->getQuotedName($this) . ' UNIQUE'
            . ' (' . implode(', ', $constraint->getQuotedColumns($this)) . ')';
    }

    /**
     * Returns the SQL snippet to drop a schema.
     *
     * @throws Exception If not supported on this platform.
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
     * @param string $identifier The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            $parts = array_map([$this, 'quoteSingleIdentifier'], explode('.', $identifier));

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
        $c = $this->getIdentifierQuoteCharacter();

        return $c . str_replace($c, $c . $c, $str) . $c;
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
     * @return array<int, string>
     */
    abstract public function getAlterTableSQL(TableDiff $diff): array;

    /**
     * @param mixed[] $columnSql
     */
    protected function onSchemaAlterTableAddColumn(Column $column, TableDiff $diff, array &$columnSql): bool
    {
        if ($this->_eventManager === null) {
            return false;
        }

        if (! $this->_eventManager->hasListeners(Events::onSchemaAlterTableAddColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableAddColumnEventArgs($column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableAddColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * @param string[] $columnSql
     */
    protected function onSchemaAlterTableRemoveColumn(Column $column, TableDiff $diff, array &$columnSql): bool
    {
        if ($this->_eventManager === null) {
            return false;
        }

        if (! $this->_eventManager->hasListeners(Events::onSchemaAlterTableRemoveColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableRemoveColumnEventArgs($column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableRemoveColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * @param string[] $columnSql
     */
    protected function onSchemaAlterTableChangeColumn(ColumnDiff $columnDiff, TableDiff $diff, array &$columnSql): bool
    {
        if ($this->_eventManager === null) {
            return false;
        }

        if (! $this->_eventManager->hasListeners(Events::onSchemaAlterTableChangeColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableChangeColumnEventArgs($columnDiff, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableChangeColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * @param string[] $columnSql
     */
    protected function onSchemaAlterTableRenameColumn(
        string $oldColumnName,
        Column $column,
        TableDiff $diff,
        array &$columnSql
    ): bool {
        if ($this->_eventManager === null) {
            return false;
        }

        if (! $this->_eventManager->hasListeners(Events::onSchemaAlterTableRenameColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableRenameColumnEventArgs($oldColumnName, $column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableRenameColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * @param string[] $sql
     */
    protected function onSchemaAlterTable(TableDiff $diff, array &$sql): bool
    {
        if ($this->_eventManager === null) {
            return false;
        }

        if (! $this->_eventManager->hasListeners(Events::onSchemaAlterTable)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableEventArgs($diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTable, $eventArgs);

        $sql = array_merge($sql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * @return string[]
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $tableName = $diff->getName($this)->getQuotedName($this);

        $sql = [];
        if ($this->supportsForeignKeyConstraints()) {
            foreach ($diff->removedForeignKeys as $foreignKey) {
                $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableName);
            }

            foreach ($diff->changedForeignKeys as $foreignKey) {
                $sql[] = $this->getDropForeignKeySQL($foreignKey->getQuotedName($this), $tableName);
            }
        }

        foreach ($diff->removedIndexes as $index) {
            $sql[] = $this->getDropIndexSQL($index->getQuotedName($this), $tableName);
        }

        foreach ($diff->changedIndexes as $index) {
            $sql[] = $this->getDropIndexSQL($index->getQuotedName($this), $tableName);
        }

        return $sql;
    }

    /**
     * @return string[]
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        $sql     = [];
        $newName = $diff->getNewName();

        if ($newName !== null) {
            $tableName = $newName->getQuotedName($this);
        } else {
            $tableName = $diff->getName($this)->getQuotedName($this);
        }

        if ($this->supportsForeignKeyConstraints()) {
            foreach ($diff->addedForeignKeys as $foreignKey) {
                $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableName);
            }

            foreach ($diff->changedForeignKeys as $foreignKey) {
                $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableName);
            }
        }

        foreach ($diff->addedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableName);
        }

        foreach ($diff->changedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableName);
        }

        foreach ($diff->renamedIndexes as $oldIndexName => $index) {
            $oldIndexName = new Identifier($oldIndexName);
            $sql          = array_merge(
                $sql,
                $this->getRenameIndexSQL($oldIndexName->getQuotedName($this), $index, $tableName)
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
     * @return string[] The sequence of SQL statements for renaming the given index.
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        return [
            $this->getDropIndexSQL($oldIndexName, $tableName),
            $this->getCreateIndexSQL($index, $tableName),
        ];
    }

    /**
     * Gets declaration of a number of columns in bulk.
     *
     * @param mixed[][] $columns A multidimensional array.
     *                           The first dimension determines the ordinal position of the column,
     *                           while the second dimension is keyed with the name of the properties
     *                           of the column being declared as array indexes. Currently, the types
     *                           of supported column properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          column. If this argument is missing the column should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this column.
     *
     *      notnull
     *          Boolean flag that indicates whether this column is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this column.
     *      collation
     *          Text value with the default COLLATION for this column.
     *      unique
     *          unique constraint
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
     * @param string  $name   The name the column to be declared.
     * @param mixed[] $column An associative array with the name of the properties
     *                        of the column being declared as array indexes. Currently, the types
     *                        of supported column properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          column. If this argument is missing the column should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this column.
     *
     *      notnull
     *          Boolean flag that indicates whether this column is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this column.
     *      collation
     *          Text value with the default COLLATION for this column.
     *      unique
     *          unique constraint
     *      check
     *          column check constraint
     *      columnDefinition
     *          a string that defines the complete column
     *
     * @return string DBMS specific SQL code portion that should be used to declare the column.
     *
     * @throws Exception
     */
    public function getColumnDeclarationSQL(string $name, array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $this->getCustomTypeDeclarationSQL($column);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($column);

            $charset = ! empty($column['charset']) ?
                ' ' . $this->getColumnCharsetDeclarationSQL($column['charset']) : '';

            $collation = ! empty($column['collation']) ?
                ' ' . $this->getColumnCollationDeclarationSQL($column['collation']) : '';

            $notnull = ! empty($column['notnull']) ? ' NOT NULL' : '';

            $unique = ! empty($column['unique']) ? ' UNIQUE' : '';

            $check = ! empty($column['check']) ? ' ' . $column['check'] : '';

            $typeDecl    = $column['type']->getSQLDeclaration($column, $this);
            $declaration = $typeDecl . $charset . $default . $notnull . $unique . $check . $collation;

            if ($this->supportsInlineColumnComments() && isset($column['comment']) && $column['comment'] !== '') {
                $declaration .= ' ' . $this->getInlineColumnCommentSQL($column['comment']);
            }
        }

        return $name . ' ' . $declaration;
    }

    /**
     * Returns the SQL snippet that declares a floating point column of arbitrary precision.
     *
     * @param mixed[] $column
     */
    public function getDecimalTypeDeclarationSQL(array $column): string
    {
        $column['precision'] = ! isset($column['precision']) || empty($column['precision'])
            ? 10 : $column['precision'];
        $column['scale']     = ! isset($column['scale']) || empty($column['scale'])
            ? 0 : $column['scale'];

        return 'NUMERIC(' . $column['precision'] . ', ' . $column['scale'] . ')';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param mixed[] $column The column definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    public function getDefaultValueDeclarationSQL(array $column): string
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

        if ($type instanceof Types\TimeType && $default === $this->getCurrentTimeSQL()) {
            return ' DEFAULT ' . $this->getCurrentTimeSQL();
        }

        if ($type instanceof Types\DateType && $default === $this->getCurrentDateSQL()) {
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
     * @param string[]|mixed[][] $definition The check definition.
     *
     * @return string DBMS specific SQL code portion needed to set a CHECK constraint.
     */
    public function getCheckDeclarationSQL(array $definition): string
    {
        $constraints = [];
        foreach ($definition as $def) {
            if (is_string($def)) {
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
     * Obtains DBMS specific SQL code portion needed to set a unique
     * constraint declaration to be used in statements like CREATE TABLE.
     *
     * @param UniqueConstraint $constraint The unique constraint definition.
     *
     * @return string DBMS specific SQL code portion needed to set a constraint.
     *
     * @throws InvalidArgumentException
     */
    public function getUniqueConstraintDeclarationSQL(UniqueConstraint $constraint): string
    {
        $columns = $constraint->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        $chunks = ['CONSTRAINT'];

        if ($constraint->getName() !== '') {
            $chunks[] = $constraint->getQuotedName($this);
        }

        $chunks[] = 'UNIQUE';

        if ($constraint->hasFlag('clustered')) {
            $chunks[] = 'CLUSTERED';
        }

        $chunks[] = sprintf('(%s)', $this->getColumnsFieldDeclarationListSQL($columns));

        return implode(' ', $chunks);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param Index $index The index definition.
     *
     * @return string DBMS specific SQL code portion needed to set an index.
     *
     * @throws InvalidArgumentException
     */
    public function getIndexDeclarationSQL(Index $index): string
    {
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException('Incomplete definition. "columns" required.');
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $index->getQuotedName($this)
            . ' (' . $this->getIndexFieldDeclarationListSQL($index) . ')' . $this->getPartialIndexSQL($index);
    }

    /**
     * Obtains SQL code portion needed to create a custom column,
     * e.g. when a column has the "columnDefinition" keyword.
     * Only "AUTOINCREMENT" and "PRIMARY KEY" are added if appropriate.
     *
     * @param mixed[] $column
     */
    public function getCustomTypeDeclarationSQL(array $column): string
    {
        return $column['columnDefinition'];
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     */
    public function getIndexFieldDeclarationListSQL(Index $index): string
    {
        return implode(', ', $index->getQuotedColumns($this));
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param mixed[] $columns
     */
    public function getColumnsFieldDeclarationListSQL(array $columns): string
    {
        $ret = [];

        foreach ($columns as $column => $definition) {
            if (is_array($definition)) {
                $ret[] = $column;
            } else {
                $ret[] = $definition;
            }
        }

        return implode(', ', $ret);
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
     * Returns the given referential action in uppercase if valid, otherwise throws an exception.
     *
     * @param string $action The foreign key referential action.
     *
     * @throws InvalidArgumentException If unknown referential action given.
     */
    public function getForeignKeyReferentialActionSQL(string $action): string
    {
        $upper = strtoupper($action);
        switch ($upper) {
            case 'CASCADE':
            case 'SET NULL':
            case 'NO ACTION':
            case 'RESTRICT':
            case 'SET DEFAULT':
                return $upper;

            default:
                throw new InvalidArgumentException(sprintf('Invalid foreign key action "%s".', $upper));
        }
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @throws InvalidArgumentException
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
     * @param string $collation The name of the collation.
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a column declaration.
     */
    public function getColumnCollationDeclarationSQL(string $collation): string
    {
        return $this->supportsColumnCollation() ? 'COLLATE ' . $collation : '';
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
     *
     * @throws InvalidArgumentException
     */
    protected function _getTransactionIsolationLevelSQL(int $level): string
    {
        switch ($level) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
                return 'READ UNCOMMITTED';

            case TransactionIsolationLevel::READ_COMMITTED:
                return 'READ COMMITTED';

            case TransactionIsolationLevel::REPEATABLE_READ:
                return 'REPEATABLE READ';

            case TransactionIsolationLevel::SERIALIZABLE:
                return 'SERIALIZABLE';

            default:
                throw new InvalidArgumentException(sprintf('Invalid isolation level "%s".', $level));
        }
    }

    /**
     * @throws Exception If not supported on this platform.
     */
    public function getListDatabasesSQL(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @throws Exception If not supported on this platform.
     */
    public function getListSequencesSQL(string $database): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     */
    abstract public function getListTablesSQL(): string;

    /**
     * Returns the SQL to list all views of a database or user.
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

    /**
     * @throws Exception If not supported on this platform.
     */
    public function getSequenceNextValSQL(string $sequence): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to create a new database.
     *
     * @param string $name The name of the database that should be created.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getCreateDatabaseSQL(string $name): string
    {
        if (! $this->supportsCreateDropDatabase()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'CREATE DATABASE ' . $name;
    }

    /**
     * Returns the SQL snippet to drop an existing database.
     *
     * @param string $name The name of the database that should be dropped.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDropDatabaseSQL(string $name): string
    {
        if (! $this->supportsCreateDropDatabase()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'DROP DATABASE ' . $name;
    }

    /**
     * Returns the SQL to set the transaction isolation level.
     */
    abstract public function getSetTransactionIsolationSQL(int $level): string;

    /**
     * Obtains DBMS specific SQL to be used to create datetime columns in
     * statements like CREATE TABLE.
     *
     * @param mixed[] $column
     */
    abstract public function getDateTimeTypeDeclarationSQL(array $column): string;

    /**
     * Obtains DBMS specific SQL to be used to create datetime with timezone offset columns.
     *
     * @param mixed[] $column
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return $this->getDateTimeTypeDeclarationSQL($column);
    }

    /**
     * Obtains DBMS specific SQL to be used to create date columns in statements
     * like CREATE TABLE.
     *
     * @param mixed[] $column
     */
    abstract public function getDateTypeDeclarationSQL(array $column): string;

    /**
     * Obtains DBMS specific SQL to be used to create time columns in statements
     * like CREATE TABLE.
     *
     * @param mixed[] $column
     */
    abstract public function getTimeTypeDeclarationSQL(array $column): string;

    /**
     * @param mixed[] $column
     */
    public function getFloatDeclarationSQL(array $column): string
    {
        return 'DOUBLE PRECISION';
    }

    /**
     * Gets the default transaction isolation level of the platform.
     *
     * @see TransactionIsolationLevel
     *
     * @return int The default isolation level.
     */
    public function getDefaultTransactionIsolationLevel(): int
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
     * Whether the platform emulates identity columns through sequences.
     *
     * Some platforms that do not support identity columns natively
     * but support sequences can emulate identity columns by using
     * sequences.
     */
    public function usesSequenceEmulatedIdentityColumns(): bool
    {
        return false;
    }

    /**
     * Returns the name of the sequence for a particular identity column in a particular table.
     *
     * @see usesSequenceEmulatedIdentityColumns
     *
     * @param string $tableName  The name of the table to return the sequence name for.
     * @param string $columnName The name of the identity column in the table to return the sequence name for.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getIdentitySequenceName(string $tableName, string $columnName): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Whether the platform supports partial indexes.
     */
    public function supportsPartialIndexes(): bool
    {
        return false;
    }

    /**
     * Whether the platform supports indexes with column length definitions.
     */
    public function supportsColumnLengthIndexes(): bool
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
     * Whether the platform supports foreign key constraints.
     */
    public function supportsForeignKeyConstraints(): bool
    {
        return true;
    }

    /**
     * Whether the platform supports database schemas.
     */
    public function supportsSchemas(): bool
    {
        return false;
    }

    /**
     * Returns the default schema name.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDefaultSchemaName(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Whether this platform supports create database.
     *
     * Some databases don't allow to create and drop databases at all or only with certain tools.
     */
    public function supportsCreateDropDatabase(): bool
    {
        return true;
    }

    /**
     * Whether this platform support to add inline column comments as postfix.
     */
    public function supportsInlineColumnComments(): bool
    {
        return false;
    }

    /**
     * Whether this platform support the proprietary syntax "COMMENT ON asset".
     */
    public function supportsCommentOnStatement(): bool
    {
        return false;
    }

    /**
     * Does this platform have native guid type.
     */
    public function hasNativeGuidType(): bool
    {
        return false;
    }

    /**
     * Does this platform have native JSON type.
     */
    public function hasNativeJsonType(): bool
    {
        return false;
    }

    /**
     * Does this platform support column collation?
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
     *
     * @throws Exception
     */
    final public function modifyLimitQuery(string $query, ?int $limit, int $offset = 0): string
    {
        if ($offset < 0) {
            throw new Exception(sprintf(
                'Offset must be a positive integer or zero, %d given.',
                $offset
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
     */
    final public function getReservedKeywordsList(): KeywordList
    {
        // Check for an existing instantiation of the keywords class.
        if ($this->_keywords === null) {
            // Store the instance so it doesn't need to be generated on every request.
            $this->_keywords = $this->createReservedKeywordsList();
        }

        return $this->_keywords;
    }

    /**
     * Creates an instance of the reserved keyword list of this platform.
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
        $c = $this->getStringLiteralQuoteCharacter();

        return $c . str_replace($c, $c . $c, $str) . $c;
    }

    /**
     * Gets the character used for string literal quoting.
     */
    public function getStringLiteralQuoteCharacter(): string
    {
        return "'";
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
            $inputString
        );

        assert(is_string($sql));

        return $sql;
    }

    /**
     * @return array<string,mixed> An associative array with the name of the properties
     *                             of the column being declared as array indexes.
     */
    private function columnToArray(Column $column): array
    {
        return array_merge($column->toArray(), [
            'name' => $column->getQuotedName($this),
            'version' => $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false,
            'comment' => $column->getComment(),
        ]);
    }

    /**
     * @internal
     */
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
     *
     * @throws Exception
     */
    public function columnsEqual(Column $column1, Column $column2): bool
    {
        $column1Array = $this->columnToArray($column1);
        $column2Array = $this->columnToArray($column2);

        // ignore explicit columnDefinition since it's not set on the Column generated by the SchemaManager
        unset($column1Array['columnDefinition']);
        unset($column2Array['columnDefinition']);

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
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Event\SchemaDropTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\Exception\NoColumnsSpecifiedForTable;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
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
use function is_int;
use function is_string;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
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

    /**
     * @deprecated Use DateIntervalUnit::INTERVAL_UNIT_SECOND.
     */
    public const DATE_INTERVAL_UNIT_SECOND = DateIntervalUnit::SECOND;

    /**
     * @deprecated Use DateIntervalUnit::MINUTE.
     */
    public const DATE_INTERVAL_UNIT_MINUTE = DateIntervalUnit::MINUTE;

    /**
     * @deprecated Use DateIntervalUnit::HOUR.
     */
    public const DATE_INTERVAL_UNIT_HOUR = DateIntervalUnit::HOUR;

    /**
     * @deprecated Use DateIntervalUnit::DAY.
     */
    public const DATE_INTERVAL_UNIT_DAY = DateIntervalUnit::DAY;

    /**
     * @deprecated Use DateIntervalUnit::WEEK.
     */
    public const DATE_INTERVAL_UNIT_WEEK = DateIntervalUnit::WEEK;

    /**
     * @deprecated Use DateIntervalUnit::MONTH.
     */
    public const DATE_INTERVAL_UNIT_MONTH = DateIntervalUnit::MONTH;

    /**
     * @deprecated Use DateIntervalUnit::QUARTER.
     */
    public const DATE_INTERVAL_UNIT_QUARTER = DateIntervalUnit::QUARTER;

    /**
     * @deprecated Use DateIntervalUnit::QUARTER.
     */
    public const DATE_INTERVAL_UNIT_YEAR = DateIntervalUnit::YEAR;

    /**
     * @deprecated Use TrimMode::UNSPECIFIED.
     */
    public const TRIM_UNSPECIFIED = TrimMode::UNSPECIFIED;

    /**
     * @deprecated Use TrimMode::LEADING.
     */
    public const TRIM_LEADING = TrimMode::LEADING;

    /**
     * @deprecated Use TrimMode::TRAILING.
     */
    public const TRIM_TRAILING = TrimMode::TRAILING;

    /**
     * @deprecated Use TrimMode::BOTH.
     */
    public const TRIM_BOTH = TrimMode::BOTH;

    /** @var string[]|null */
    protected $doctrineTypeMapping = null;

    /**
     * Contains a list of all columns that should generate parseable column comments for type-detection
     * in reverse engineering scenarios.
     *
     * @var string[]|null
     */
    protected $doctrineTypeComments = null;

    /** @var EventManager */
    protected $_eventManager;

    /**
     * Holds the KeywordList instance for the current platform.
     *
     * @var KeywordList|null
     */
    protected $_keywords;

    public function __construct()
    {
    }

    /**
     * Sets the EventManager used by the Platform.
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * Gets the EventManager used by the Platform.
     *
     * @return EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Returns the SQL snippet that declares a boolean column.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    abstract public function getBooleanTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet that declares a 4 byte integer column.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    abstract public function getIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet that declares an 8 byte integer column.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    abstract public function getBigIntTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet that declares a 2 byte integer column.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet that declares common properties of an integer column.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    abstract protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Lazy load Doctrine Type Mappings.
     *
     * @return void
     */
    abstract protected function initializeDoctrineTypeMappings();

    /**
     * Initializes Doctrine Type Mappings with the platform defaults
     * and with all additional type mappings.
     *
     * @return void
     */
    private function initializeAllDoctrineTypeMappings()
    {
        $this->initializeDoctrineTypeMappings();

        foreach (Type::getTypesMap() as $typeName => $className) {
            foreach (Type::getType($typeName)->getMappedDatabaseTypes($this) as $dbType) {
                $this->doctrineTypeMapping[$dbType] = $typeName;
            }
        }
    }

    /**
     * Returns the SQL snippet used to declare a VARCHAR column type.
     *
     * @param mixed[] $field
     *
     * @return string
     */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if (! isset($field['length'])) {
            $field['length'] = $this->getVarcharDefaultLength();
        }

        $fixed = $field['fixed'] ?? false;

        $maxLength = $fixed
            ? $this->getCharMaxLength()
            : $this->getVarcharMaxLength();

        if ($field['length'] > $maxLength) {
            return $this->getClobTypeDeclarationSQL($field);
        }

        return $this->getVarcharTypeDeclarationSQLSnippet($field['length'], $fixed);
    }

    /**
     * Returns the SQL snippet used to declare a BINARY/VARBINARY column type.
     *
     * @param mixed[] $field The column definition.
     *
     * @return string
     */
    public function getBinaryTypeDeclarationSQL(array $field)
    {
        if (! isset($field['length'])) {
            $field['length'] = $this->getBinaryDefaultLength();
        }

        $fixed = $field['fixed'] ?? false;

        return $this->getBinaryTypeDeclarationSQLSnippet($field['length'], $fixed);
    }

    /**
     * Returns the SQL snippet to declare a GUID/UUID field.
     *
     * By default this maps directly to a CHAR(36) and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param mixed[] $field
     *
     * @return string
     */
    public function getGuidTypeDeclarationSQL(array $field)
    {
        $field['length'] = 36;
        $field['fixed']  = true;

        return $this->getVarcharTypeDeclarationSQL($field);
    }

    /**
     * Returns the SQL snippet to declare a JSON field.
     *
     * By default this maps directly to a CLOB and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param mixed[] $field
     *
     * @return string
     */
    public function getJsonTypeDeclarationSQL(array $field)
    {
        return $this->getClobTypeDeclarationSQL($field);
    }

    /**
     * @param int  $length
     * @param bool $fixed
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        throw NotSupported::new('VARCHARs not supported by Platform.');
    }

    /**
     * Returns the SQL snippet used to declare a BINARY/VARBINARY column type.
     *
     * @param int  $length The length of the column.
     * @param bool $fixed  Whether the column length is fixed.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        throw NotSupported::new('BINARY/VARBINARY column types are not supported by this platform.');
    }

    /**
     * Returns the SQL snippet used to declare a CLOB column type.
     *
     * @param mixed[] $field
     *
     * @return string
     */
    abstract public function getClobTypeDeclarationSQL(array $field);

    /**
     * Returns the SQL Snippet used to declare a BLOB column type.
     *
     * @param mixed[] $field
     *
     * @return string
     */
    abstract public function getBlobTypeDeclarationSQL(array $field);

    /**
     * Gets the name of the platform.
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param string $dbType
     * @param string $doctrineType
     *
     * @throws DBALException If the type is not found.
     */
    public function registerDoctrineTypeMapping($dbType, $doctrineType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        if (! Types\Type::hasType($doctrineType)) {
            throw TypeNotFound::new($doctrineType);
        }

        $dbType                             = strtolower($dbType);
        $this->doctrineTypeMapping[$dbType] = $doctrineType;

        $doctrineType = Type::getType($doctrineType);

        if (! $doctrineType->requiresSQLCommentHint($this)) {
            return;
        }

        $this->markDoctrineTypeCommented($doctrineType);
    }

    /**
     * Gets the Doctrine type that is mapped for the given database column type.
     *
     * @param string $dbType
     *
     * @return string
     *
     * @throws DBALException
     */
    public function getDoctrineTypeMapping($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if (! isset($this->doctrineTypeMapping[$dbType])) {
            throw new DBALException('Unknown database type ' . $dbType . ' requested, ' . static::class . ' may not support it.');
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Checks if a database type is currently supported by this platform.
     *
     * @param string $dbType
     *
     * @return bool
     */
    public function hasDoctrineTypeMappingFor($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        return isset($this->doctrineTypeMapping[$dbType]);
    }

    /**
     * Initializes the Doctrine Type comments instance variable for in_array() checks.
     *
     * @return void
     */
    protected function initializeCommentedDoctrineTypes()
    {
        $this->doctrineTypeComments = [];

        foreach (Type::getTypesMap() as $typeName => $className) {
            $type = Type::getType($typeName);

            if (! $type->requiresSQLCommentHint($this)) {
                continue;
            }

            $this->doctrineTypeComments[] = $typeName;
        }
    }

    /**
     * Is it necessary for the platform to add a parsable type comment to allow reverse engineering the given type?
     *
     * @return bool
     */
    public function isCommentedDoctrineType(Type $doctrineType)
    {
        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        assert(is_array($this->doctrineTypeComments));

        return in_array($doctrineType->getName(), $this->doctrineTypeComments);
    }

    /**
     * Marks this type as to be commented in ALTER TABLE and CREATE TABLE statements.
     *
     * @param string|Type $doctrineType
     *
     * @return void
     */
    public function markDoctrineTypeCommented($doctrineType)
    {
        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        assert(is_array($this->doctrineTypeComments));

        $this->doctrineTypeComments[] = $doctrineType instanceof Type ? $doctrineType->getName() : $doctrineType;
    }

    /**
     * Gets the comment to append to a column comment that helps parsing this type in reverse engineering.
     *
     * @return string
     */
    public function getDoctrineTypeComment(Type $doctrineType)
    {
        return '(DC2Type:' . $doctrineType->getName() . ')';
    }

    /**
     * Gets the comment of a passed column modified by potential doctrine type comment hints.
     *
     * @return string|null
     */
    protected function getColumnComment(Column $column)
    {
        $comment = $column->getComment();

        if ($this->isCommentedDoctrineType($column->getType())) {
            $comment .= $this->getDoctrineTypeComment($column->getType());
        }

        return $comment;
    }

    /**
     * Gets the character used for identifier quoting.
     *
     * @return string
     */
    public function getIdentifierQuoteCharacter()
    {
        return '"';
    }

    /**
     * Gets the string portion that starts an SQL comment.
     *
     * @return string
     */
    public function getSqlCommentStartString()
    {
        return '--';
    }

    /**
     * Gets the string portion that ends an SQL comment.
     *
     * @return string
     */
    public function getSqlCommentEndString()
    {
        return "\n";
    }

    /**
     * Gets the maximum length of a char field.
     */
    public function getCharMaxLength() : int
    {
        return $this->getVarcharMaxLength();
    }

    /**
     * Gets the maximum length of a varchar field.
     *
     * @return int
     */
    public function getVarcharMaxLength()
    {
        return 4000;
    }

    /**
     * Gets the default length of a varchar field.
     *
     * @return int
     */
    public function getVarcharDefaultLength()
    {
        return 255;
    }

    /**
     * Gets the maximum length of a binary field.
     *
     * @return int
     */
    public function getBinaryMaxLength()
    {
        return 4000;
    }

    /**
     * Gets the default length of a binary field.
     *
     * @return int
     */
    public function getBinaryDefaultLength()
    {
        return 255;
    }

    /**
     * Gets all SQL wildcard characters of the platform.
     *
     * @return string[]
     */
    public function getWildcards()
    {
        return ['%', '_'];
    }

    /**
     * Returns the regular expression operator.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getRegexpExpression() : string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to get the average value of a column.
     *
     * @param string $value SQL expression producing the value.
     */
    public function getAvgExpression(string $value) : string
    {
        return 'AVG(' . $value . ')';
    }

    /**
     * Returns the SQL snippet to get the number of rows (without a NULL value) of a column.
     *
     * If a '*' is used instead of a column the number of selected rows is returned.
     *
     * @param string $expression The expression to count.
     */
    public function getCountExpression(string $expression) : string
    {
        return 'COUNT(' . $expression . ')';
    }

    /**
     * Returns the SQL snippet to get the maximum value in a set of values.
     *
     * @param string $value SQL expression producing the value.
     */
    public function getMaxExpression(string $value) : string
    {
        return 'MAX(' . $value . ')';
    }

    /**
     * Returns the SQL snippet to get the minimum value in a set of values.
     *
     * @param string $value SQL expression producing the value.
     */
    public function getMinExpression(string $value) : string
    {
        return 'MIN(' . $value . ')';
    }

    /**
     * Returns the SQL snippet to get the total sum of the values in a set.
     *
     * @param string $value SQL expression producing the value.
     */
    public function getSumExpression(string $value) : string
    {
        return 'SUM(' . $value . ')';
    }

    // scalar functions

    /**
     * Returns the SQL snippet to get the md5 sum of the value.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getMd5Expression(string $string) : string
    {
        return 'MD5(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to get the length of a text field.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getLengthExpression(string $string) : string
    {
        return 'LENGTH(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to get the square root of the value.
     *
     * @param string $number SQL expression producing the number.
     */
    public function getSqrtExpression(string $number) : string
    {
        return 'SQRT(' . $number . ')';
    }

    /**
     * Returns the SQL snippet to round a number to the number of decimals specified.
     *
     * @param string $number   SQL expression producing the number to round.
     * @param string $decimals SQL expression producing the number of decimals.
     */
    public function getRoundExpression(string $number, string $decimals = '0') : string
    {
        return 'ROUND(' . $number . ', ' . $decimals . ')';
    }

    /**
     * Returns the SQL snippet to get the remainder of the operation of division of dividend by divisor.
     *
     * @param string $dividend SQL expression producing the dividend.
     * @param string $divisor  SQL expression producing the divisor.
     */
    public function getModExpression(string $dividend, string $divisor) : string
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
    public function getTrimExpression(string $str, int $mode = TrimMode::UNSPECIFIED, ?string $char = null) : string
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
                        'The value of $mode is expected to be one of the TrimMode constants, %d given',
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
     * Returns the SQL snippet to trim trailing space characters from the string.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getRtrimExpression(string $string) : string
    {
        return 'RTRIM(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to trim leading space characters from the string.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getLtrimExpression(string $string) : string
    {
        return 'LTRIM(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to change all characters from the string to uppercase,
     * according to the current character set mapping.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getUpperExpression(string $string) : string
    {
        return 'UPPER(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to change all characters from the string to lowercase,
     * according to the current character set mapping.
     *
     * @param string $string SQL expression producing the string.
     */
    public function getLowerExpression(string $string) : string
    {
        return 'LOWER(' . $string . ')';
    }

    /**
     * Returns the SQL snippet to get the position of the first occurrence of the substring in the string.
     *
     * @param string      $string    SQL expression producing the string to locate the substring in.
     * @param string      $substring SQL expression producing the substring to locate.
     * @param string|null $start     SQL expression producing the position to start at.
     *                               Defaults to the beginning of the string.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getLocateExpression(string $string, string $substring, ?string $start = null) : string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to get the current system date.
     */
    public function getNowExpression() : string
    {
        return 'NOW()';
    }

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
    public function getSubstringExpression(string $string, string $start, ?string $length = null) : string
    {
        if ($length === null) {
            return sprintf('SUBSTRING(%s FROM %s)', $string, $start);
        }

        return sprintf('SUBSTRING(%s FROM %s FOR %s)', $string, $start, $length);
    }

    /**
     * Returns a SQL snippet to concatenate the given strings.
     *
     * @param string[] ...$string
     */
    public function getConcatExpression(string ...$string) : string
    {
        return implode(' || ', $string);
    }

    /**
     * Returns the SQL for a logical not.
     *
     * @param string $value SQL expression producing the value to negate.
     */
    public function getNotExpression(string $value) : string
    {
        return 'NOT(' . $value . ')';
    }

    /**
     * Returns the SQL that checks if an expression is null.
     *
     * @param string $value SQL expression producing the to be compared to null.
     */
    public function getIsNullExpression(string $value) : string
    {
        return $value . ' IS NULL';
    }

    /**
     * Returns the SQL that checks if an expression is not null.
     *
     * @param string $value SQL expression producing the to be compared to null.
     */
    public function getIsNotNullExpression(string $value) : string
    {
        return $value . ' IS NOT NULL';
    }

    /**
     * Returns the SQL that checks if an expression evaluates to a value between two values.
     *
     * The parameter $value is checked if it is between $min and $max.
     *
     * Note: There is a slight difference in the way BETWEEN works on some databases.
     * http://www.w3schools.com/sql/sql_between.asp. If you want complete database
     * independence you should avoid using between().
     *
     * @param string $value SQL expression producing the value to compare.
     * @param string $min   SQL expression producing the lower value to compare with.
     * @param string $max   SQL expression producing the higher value to compare with.
     */
    public function getBetweenExpression(string $value, string $min, string $max) : string
    {
        return $value . ' BETWEEN ' . $min . ' AND ' . $max;
    }

    /**
     * Returns the SQL to get the arccosine of a value.
     *
     * @param string $number SQL expression producing the number.
     */
    public function getAcosExpression(string $number) : string
    {
        return 'ACOS(' . $number . ')';
    }

    /**
     * Returns the SQL to get the sine of a value.
     *
     * @param string $number SQL expression producing the number.
     */
    public function getSinExpression(string $number) : string
    {
        return 'SIN(' . $number . ')';
    }

    /**
     * Returns the SQL to get the PI value.
     */
    public function getPiExpression() : string
    {
        return 'PI()';
    }

    /**
     * Returns the SQL to get the cosine of a value.
     *
     * @param string $number SQL expression producing the number.
     */
    public function getCosExpression(string $number) : string
    {
        return 'COS(' . $number . ')';
    }

    /**
     * Returns the SQL to calculate the difference in days between the two passed dates.
     *
     * Computes diff = date1 - date2.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateDiffExpression(string $date1, string $date2) : string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to add the number of given seconds to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddSecondsExpression(string $date, string $seconds) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to subtract the number of given seconds from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $seconds SQL expression producing the number of seconds.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubSecondsExpression(string $date, string $seconds) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to add the number of given minutes to a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddMinutesExpression(string $date, string $minutes) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to subtract the number of given minutes from a date.
     *
     * @param string $date    SQL expression producing the date.
     * @param string $minutes SQL expression producing the number of minutes.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubMinutesExpression(string $date, string $minutes) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to add the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddHourExpression(string $date, string $hours) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to subtract the number of given hours to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $hours SQL expression producing the number of hours.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubHourExpression(string $date, string $hours) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to add the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddDaysExpression(string $date, string $days) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to subtract the number of given days to a date.
     *
     * @param string $date SQL expression producing the date.
     * @param string $days SQL expression producing the number of days.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubDaysExpression(string $date, string $days) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to add the number of given weeks to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddWeeksExpression(string $date, string $weeks) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to subtract the number of given weeks from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $weeks SQL expression producing the number of weeks.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubWeeksExpression(string $date, string $weeks) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to add the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddMonthExpression(string $date, string $months) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to subtract the number of given months to a date.
     *
     * @param string $date   SQL expression producing the date.
     * @param string $months SQL expression producing the number of months.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubMonthExpression(string $date, string $months) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to add the number of given quarters to a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddQuartersExpression(string $date, string $quarters) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to subtract the number of given quarters from a date.
     *
     * @param string $date     SQL expression producing the date.
     * @param string $quarters SQL expression producing the number of quarters.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubQuartersExpression(string $date, string $quarters) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to add the number of given years to a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateAddYearsExpression(string $date, string $years) : string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $years, DateIntervalUnit::YEAR);
    }

    /**
     * Returns the SQL to subtract the number of given years from a date.
     *
     * @param string $date  SQL expression producing the date.
     * @param string $years SQL expression producing the number of years.
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateSubYearsExpression(string $date, string $years) : string
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
     *
     * @throws DBALException If not supported on this platform.
     */
    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, string $unit) : string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Generates the SQL expression which represents the given date interval multiplied by a number
     *
     * @param string $interval   SQL expression describing the interval value
     * @param int    $multiplier Interval multiplier
     *
     * @throws DBALException
     */
    protected function multiplyInterval(string $interval, int $multiplier) : string
    {
        return sprintf('(%s * %d)', $interval, $multiplier);
    }

    /**
     * Returns the SQL bit AND comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function getBitAndComparisonExpression(string $value1, string $value2) : string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * Returns the SQL bit OR comparison expression.
     *
     * @param string $value1 SQL expression producing the first value.
     * @param string $value2 SQL expression producing the second value.
     */
    public function getBitOrComparisonExpression(string $value1, string $value2) : string
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    /**
     * Returns the FOR UPDATE expression.
     *
     * @return string
     */
    public function getForUpdateSQL()
    {
        return 'FOR UPDATE';
    }

    /**
     * Honors that some SQL vendors such as MsSql use table hints for locking instead of the ANSI SQL FOR UPDATE specification.
     *
     * @param string   $fromClause The FROM clause to append the hint for the given lock mode to.
     * @param int|null $lockMode   One of the Doctrine\DBAL\LockMode::* constants. If null is given, nothing will
     *                             be appended to the FROM clause.
     *
     * @return string
     */
    public function appendLockHint($fromClause, $lockMode)
    {
        return $fromClause;
    }

    /**
     * Returns the SQL snippet to append to any SELECT statement which locks rows in shared read lock.
     *
     * This defaults to the ANSI SQL "FOR UPDATE", which is an exclusive lock (Write). Some database
     * vendors allow to lighten this constraint up to be a real read lock.
     *
     * @return string
     */
    public function getReadLockSQL()
    {
        return $this->getForUpdateSQL();
    }

    /**
     * Returns the SQL snippet to append to any SELECT statement which obtains an exclusive lock on the rows.
     *
     * The semantics of this lock mode should equal the SELECT .. FOR UPDATE of the ANSI SQL standard.
     *
     * @return string
     */
    public function getWriteLockSQL()
    {
        return $this->getForUpdateSQL();
    }

    /**
     * Returns the SQL snippet to drop an existing database.
     *
     * @param string $database The name of the database that should be dropped.
     *
     * @return string
     */
    public function getDropDatabaseSQL($database)
    {
        return 'DROP DATABASE ' . $database;
    }

    /**
     * Returns the SQL snippet to drop an existing table.
     *
     * @param Table|string $table
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getDropTableSQL($table)
    {
        $tableArg = $table;

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        if (! is_string($table)) {
            throw new InvalidArgumentException('getDropTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        if ($this->_eventManager !== null && $this->_eventManager->hasListeners(Events::onSchemaDropTable)) {
            $eventArgs = new SchemaDropTableEventArgs($tableArg, $this);
            $this->_eventManager->dispatchEvent(Events::onSchemaDropTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                $sql = $eventArgs->getSql();

                if ($sql === null) {
                    throw new UnexpectedValueException('Default implementation of DROP TABLE was overridden with NULL');
                }

                return $sql;
            }
        }

        return 'DROP TABLE ' . $table;
    }

    /**
     * Returns the SQL to safely drop a temporary table WITHOUT implicitly committing an open transaction.
     *
     * @param Table|string $table
     *
     * @return string
     */
    public function getDropTemporaryTableSQL($table)
    {
        return $this->getDropTableSQL($table);
    }

    /**
     * Returns the SQL to drop an index from a table.
     *
     * @param Index|string $index
     * @param Table|string $table
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getDropIndexSQL($index, $table = null)
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif (! is_string($index)) {
            throw new InvalidArgumentException('AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        return 'DROP INDEX ' . $index;
    }

    /**
     * Returns the SQL to drop a constraint.
     *
     * @param Constraint|string $constraint
     * @param Table|string      $table
     *
     * @return string
     */
    public function getDropConstraintSQL($constraint, $table)
    {
        if (! $constraint instanceof Constraint) {
            $constraint = new Identifier($constraint);
        }

        if (! $table instanceof Table) {
            $table = new Identifier($table);
        }

        $constraint = $constraint->getQuotedName($this);
        $table      = $table->getQuotedName($this);

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $constraint;
    }

    /**
     * Returns the SQL to drop a foreign key.
     *
     * @param ForeignKeyConstraint|string $foreignKey
     * @param Table|string                $table
     *
     * @return string
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if (! $foreignKey instanceof ForeignKeyConstraint) {
            $foreignKey = new Identifier($foreignKey);
        }

        if (! $table instanceof Table) {
            $table = new Identifier($table);
        }

        $foreignKey = $foreignKey->getQuotedName($this);
        $table      = $table->getQuotedName($this);

        return 'ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $foreignKey;
    }

    /**
     * Returns the SQL statement(s) to create a table with the specified name, columns and constraints
     * on this platform.
     *
     * @param int $createFlags
     *
     * @return string[] The sequence of SQL statements.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        if (! is_int($createFlags)) {
            throw new InvalidArgumentException('Second argument of AbstractPlatform::getCreateTableSQL() has to be integer.');
        }

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
                /** @var $index Index */
                if (! $index->isPrimary()) {
                    $options['indexes'][$index->getQuotedName($this)] = $index;

                    continue;
                }

                $options['primary']       = $index->getQuotedColumns($this);
                $options['primary_index'] = $index;
            }

            foreach ($table->getUniqueConstraints() as $uniqueConstraint) {
                /** @var UniqueConstraint $uniqueConstraint */
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
            if ($this->_eventManager !== null && $this->_eventManager->hasListeners(Events::onSchemaCreateTableColumn)) {
                $eventArgs = new SchemaCreateTableColumnEventArgs($column, $table, $this);
                $this->_eventManager->dispatchEvent(Events::onSchemaCreateTableColumn, $eventArgs);

                $columnSql = array_merge($columnSql, $eventArgs->getSql());

                if ($eventArgs->isDefaultPrevented()) {
                    continue;
                }
            }

            $columnData            = $column->toArray();
            $columnData['name']    = $column->getQuotedName($this);
            $columnData['version'] = $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false;
            $columnData['comment'] = $this->getColumnComment($column);

            if ($columnData['type'] instanceof Types\StringType && $columnData['length'] === null) {
                $columnData['length'] = 255;
            }

            if (in_array($column->getName(), $options['primary'])) {
                $columnData['primary'] = true;
            }

            $columns[$columnData['name']] = $columnData;
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
            foreach ($table->getColumns() as $column) {
                $comment = $this->getColumnComment($column);

                if ($comment === null || $comment === '') {
                    continue;
                }

                $sql[] = $this->getCommentOnColumnSQL($tableName, $column->getQuotedName($this), $comment);
            }
        }

        return array_merge($sql, $columnSql);
    }

    /**
     * @param string      $tableName
     * @param string      $columnName
     * @param string|null $comment
     *
     * @return string
     */
    public function getCommentOnColumnSQL($tableName, $columnName, $comment)
    {
        $tableName  = new Identifier($tableName);
        $columnName = new Identifier($columnName);

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s',
            $tableName->getQuotedName($this),
            $columnName->getQuotedName($this),
            $this->quoteStringLiteral((string) $comment)
        );
    }

    /**
     * Returns the SQL to create inline comment on a column.
     *
     * @param string $comment
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getInlineColumnCommentSQL($comment)
    {
        if (! $this->supportsInlineColumnComments()) {
            throw NotSupported::new(__METHOD__);
        }

        return 'COMMENT ' . $this->quoteStringLiteral($comment);
    }

    /**
     * Returns the SQL used to create a table.
     *
     * @param string    $tableName
     * @param mixed[][] $columns
     * @param mixed[]   $options
     *
     * @return string[]
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = [])
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY(' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        $sql[] = $query;

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }

    /**
     * @return string
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE TEMPORARY TABLE';
    }

    /**
     * Returns the SQL to create a sequence on this platform.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to change a sequence on this platform.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to create a constraint on a table on this platform.
     *
     * @param Table|string $table
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $query = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $constraint->getQuotedName($this);

        $columnList = '(' . implode(', ', $constraint->getQuotedColumns($this)) . ')';

        $referencesClause = '';
        if ($constraint instanceof Index) {
            if ($constraint->isPrimary()) {
                $query .= ' PRIMARY KEY';
            } elseif ($constraint->isUnique()) {
                $query .= ' UNIQUE';
            } else {
                throw new InvalidArgumentException(
                    'Can only create primary or unique constraints, no common indexes with getCreateConstraintSQL().'
                );
            }
        } elseif ($constraint instanceof ForeignKeyConstraint) {
            $query .= ' FOREIGN KEY';

            $referencesClause = ' REFERENCES ' . $constraint->getQuotedForeignTableName($this) .
                ' (' . implode(', ', $constraint->getQuotedForeignColumns($this)) . ')';
        }
        $query .= ' ' . $columnList . $referencesClause;

        return $query;
    }

    /**
     * Returns the SQL to create an index on a table on this platform.
     *
     * @param Table|string $table The name of the table on which the index is to be created.
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getCreateIndexSQL(Index $index, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }
        $name    = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
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
     *
     * @return string
     */
    protected function getPartialIndexSQL(Index $index)
    {
        if ($this->supportsPartialIndexes() && $index->hasOption('where')) {
            return ' WHERE ' . $index->getOption('where');
        }

        return '';
    }

    /**
     * Adds additional flags for index generation.
     *
     * @return string
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        return $index->isUnique() ? 'UNIQUE ' : '';
    }

    /**
     * Returns the SQL to create an unnamed primary key constraint.
     *
     * @param Table|string $table
     *
     * @return string
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . $this->getIndexFieldDeclarationListSQL($index) . ')';
    }

    /**
     * Returns the SQL to create a named schema.
     *
     * @param string $schemaName
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getCreateSchemaSQL($schemaName)
    {
        throw NotSupported::new(__METHOD__);
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
     * @param string $str The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteIdentifier($str)
    {
        if (strpos($str, '.') !== false) {
            $parts = array_map([$this, 'quoteSingleIdentifier'], explode('.', $str));

            return implode('.', $parts);
        }

        return $this->quoteSingleIdentifier($str);
    }

    /**
     * Quotes a single identifier (no dot chain separation).
     *
     * @param string $str The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteSingleIdentifier($str)
    {
        $c = $this->getIdentifierQuoteCharacter();

        return $c . str_replace($c, $c . $c, $str) . $c;
    }

    /**
     * Returns the SQL to create a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key constraint.
     * @param Table|string         $table      The name of the table on which the foreign key is to be created.
     *
     * @return string
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' ADD ' . $this->getForeignKeyDeclarationSQL($foreignKey);
    }

    /**
     * Gets the SQL statements for altering an existing table.
     *
     * This method returns an array of SQL statements, since some platforms need several statements.
     *
     * @return string[]
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param mixed[] $columnSql
     *
     * @return bool
     */
    protected function onSchemaAlterTableAddColumn(Column $column, TableDiff $diff, &$columnSql)
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
     *
     * @return bool
     */
    protected function onSchemaAlterTableRemoveColumn(Column $column, TableDiff $diff, &$columnSql)
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
     *
     * @return bool
     */
    protected function onSchemaAlterTableChangeColumn(ColumnDiff $columnDiff, TableDiff $diff, &$columnSql)
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
     * @param string   $oldColumnName
     * @param string[] $columnSql
     *
     * @return bool
     */
    protected function onSchemaAlterTableRenameColumn($oldColumnName, Column $column, TableDiff $diff, &$columnSql)
    {
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
     *
     * @return bool
     */
    protected function onSchemaAlterTable(TableDiff $diff, &$sql)
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
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $tableName = $diff->getName($this)->getQuotedName($this);

        $sql = [];
        if ($this->supportsForeignKeyConstraints()) {
            foreach ($diff->removedForeignKeys as $foreignKey) {
                $sql[] = $this->getDropForeignKeySQL($foreignKey, $tableName);
            }
            foreach ($diff->changedForeignKeys as $foreignKey) {
                $sql[] = $this->getDropForeignKeySQL($foreignKey, $tableName);
            }
        }

        foreach ($diff->removedIndexes as $index) {
            $sql[] = $this->getDropIndexSQL($index, $tableName);
        }
        foreach ($diff->changedIndexes as $index) {
            $sql[] = $this->getDropIndexSQL($index, $tableName);
        }

        return $sql;
    }

    /**
     * @return string[]
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $sql     = [];
        $newName = $diff->getNewName();

        if ($newName !== false) {
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
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        return [
            $this->getDropIndexSQL($oldIndexName, $tableName),
            $this->getCreateIndexSQL($index, $tableName),
        ];
    }

    /**
     * Common code for alter table statement generation that updates the changed Index and Foreign Key definitions.
     *
     * @return string[]
     */
    protected function _getAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        return array_merge($this->getPreAlterTableIndexForeignKeySQL($diff), $this->getPostAlterTableIndexForeignKeySQL($diff));
    }

    /**
     * Gets declaration of a number of fields in bulk.
     *
     * @param mixed[][] $fields A multidimensional associative array.
     *                          The first dimension determines the field name, while the second
     *                          dimension is keyed with the name of the properties
     *                          of the field being declared as array indexes. Currently, the types
     *                          of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this field.
     *      collation
     *          Text value with the default COLLATION for this field.
     *      unique
     *          unique constraint
     *
     * @return string
     */
    public function getColumnDeclarationListSQL(array $fields)
    {
        $queryFields = [];

        foreach ($fields as $fieldName => $field) {
            $queryFields[] = $this->getColumnDeclarationSQL($fieldName, $field);
        }

        return implode(', ', $queryFields);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to declare a generic type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string  $name  The name the field to be declared.
     * @param mixed[] $field An associative array with the name of the properties
     *                       of the field being declared as array indexes. Currently, the types
     *                       of supported field properties are as follows:
     *
     *      length
     *          Integer value that determines the maximum length of the text
     *          field. If this argument is missing the field should be
     *          declared to have the longest length allowed by the DBMS.
     *
     *      default
     *          Text value to be used as default for this field.
     *
     *      notnull
     *          Boolean flag that indicates whether this field is constrained
     *          to not be set to null.
     *      charset
     *          Text value with the default CHARACTER SET for this field.
     *      collation
     *          Text value with the default COLLATION for this field.
     *      unique
     *          unique constraint
     *      check
     *          column check constraint
     *      columnDefinition
     *          a string that defines the complete column
     *
     * @return string DBMS specific SQL code portion that should be used to declare the column.
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($field);

            $charset = isset($field['charset']) && $field['charset'] ?
                ' ' . $this->getColumnCharsetDeclarationSQL($field['charset']) : '';

            $collation = isset($field['collation']) && $field['collation'] ?
                ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';

            $notnull = isset($field['notnull']) && $field['notnull'] ? ' NOT NULL' : '';

            $unique = isset($field['unique']) && $field['unique'] ?
                ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = isset($field['check']) && $field['check'] ?
                ' ' . $field['check'] : '';

            $typeDecl  = $field['type']->getSQLDeclaration($field, $this);
            $columnDef = $typeDecl . $charset . $default . $notnull . $unique . $check . $collation;

            if ($this->supportsInlineColumnComments() && isset($field['comment']) && $field['comment'] !== '') {
                $columnDef .= ' ' . $this->getInlineColumnCommentSQL($field['comment']);
            }
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * Returns the SQL snippet that declares a floating point column of arbitrary precision.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    public function getDecimalTypeDeclarationSQL(array $columnDef)
    {
        $columnDef['precision'] = ! isset($columnDef['precision']) || empty($columnDef['precision'])
            ? 10 : $columnDef['precision'];
        $columnDef['scale']     = ! isset($columnDef['scale']) || empty($columnDef['scale'])
            ? 0 : $columnDef['scale'];

        return 'NUMERIC(' . $columnDef['precision'] . ', ' . $columnDef['scale'] . ')';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param mixed[] $field The field definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        if (! isset($field['default'])) {
            return empty($field['notnull']) ? ' DEFAULT NULL' : '';
        }

        $default = $field['default'];

        if (! isset($field['type'])) {
            return " DEFAULT '" . $default . "'";
        }

        $type = $field['type'];

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
            return " DEFAULT '" . $this->convertBooleans($default) . "'";
        }

        return " DEFAULT '" . $default . "'";
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a CHECK constraint
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param string[]|mixed[][] $definition The check definition.
     *
     * @return string DBMS specific SQL code portion needed to set a CHECK constraint.
     */
    public function getCheckDeclarationSQL(array $definition)
    {
        $constraints = [];
        foreach ($definition as $field => $def) {
            if (is_string($def)) {
                $constraints[] = 'CHECK (' . $def . ')';
            } else {
                if (isset($def['min'])) {
                    $constraints[] = 'CHECK (' . $field . ' >= ' . $def['min'] . ')';
                }

                if (isset($def['max'])) {
                    $constraints[] = 'CHECK (' . $field . ' <= ' . $def['max'] . ')';
                }
            }
        }

        return implode(', ', $constraints);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a unique
     * constraint declaration to be used in statements like CREATE TABLE.
     *
     * @param string           $name       The name of the unique constraint.
     * @param UniqueConstraint $constraint The unique constraint definition.
     *
     * @return string DBMS specific SQL code portion needed to set a constraint.
     *
     * @throws InvalidArgumentException
     */
    public function getUniqueConstraintDeclarationSQL($name, UniqueConstraint $constraint)
    {
        $columns = $constraint->getColumns();
        $name    = new Identifier($name);

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        $flags = ['UNIQUE'];

        if ($constraint->hasFlag('clustered')) {
            $flags[] = 'CLUSTERED';
        }

        $constraintName  = $name->getQuotedName($this);
        $constraintName  = ! empty($constraintName) ? $constraintName . ' ' : '';
        $columnListNames = $this->getIndexFieldDeclarationListSQL($columns);

        return sprintf('CONSTRAINT %s%s (%s)', $constraintName, implode(' ', $flags), $columnListNames);
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param string $name  The name of the index.
     * @param Index  $index The index definition.
     *
     * @return string DBMS specific SQL code portion needed to set an index.
     *
     * @throws InvalidArgumentException
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        $columns = $index->getColumns();
        $name    = new Identifier($name);

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name->getQuotedName($this) . ' ('
            . $this->getIndexFieldDeclarationListSQL($index)
            . ')' . $this->getPartialIndexSQL($index);
    }

    /**
     * Obtains SQL code portion needed to create a custom column,
     * e.g. when a field has the "columnDefinition" keyword.
     * Only "AUTOINCREMENT" and "PRIMARY KEY" are added if appropriate.
     *
     * @param mixed[] $columnDef
     *
     * @return string
     */
    public function getCustomTypeDeclarationSQL(array $columnDef)
    {
        return $columnDef['columnDefinition'];
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param mixed[]|Index $columnsOrIndex array declaration is deprecated, prefer passing Index to this method
     */
    public function getIndexFieldDeclarationListSQL($columnsOrIndex) : string
    {
        if ($columnsOrIndex instanceof Index) {
            return implode(', ', $columnsOrIndex->getQuotedColumns($this));
        }

        if (! is_array($columnsOrIndex)) {
            throw new InvalidArgumentException('Fields argument should be an Index or array.');
        }

        $ret = [];

        foreach ($columnsOrIndex as $column => $definition) {
            if (is_array($definition)) {
                $ret[] = $column;
            } else {
                $ret[] = $definition;
            }
        }

        return implode(', ', $ret);
    }

    /**
     * Returns the required SQL string that fits between CREATE ... TABLE
     * to create the table as a temporary table.
     *
     * Should be overridden in driver classes to return the correct string for the
     * specific database type.
     *
     * The default is to return the string "TEMPORARY" - this will result in a
     * SQL error for any database that does not support temporary tables, or that
     * requires a different SQL command from "CREATE TEMPORARY TABLE".
     *
     * @return string The string required to be placed between "CREATE" and "TABLE"
     *                to generate a temporary table, if possible.
     */
    public function getTemporaryTableSQL()
    {
        return 'TEMPORARY';
    }

    /**
     * Some vendors require temporary table names to be qualified specially.
     *
     * @param string $tableName
     *
     * @return string
     */
    public function getTemporaryTableName($tableName)
    {
        return $tableName;
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @return string DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     *                of a field declaration.
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
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
     *
     * @return string
     */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey)
    {
        $query = '';
        if ($this->supportsForeignKeyOnUpdate() && $foreignKey->hasOption('onUpdate')) {
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
     * @return string
     *
     * @throws InvalidArgumentException If unknown referential action given.
     */
    public function getForeignKeyReferentialActionSQL($action)
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
                throw new InvalidArgumentException('Invalid foreign key action: ' . $upper);
        }
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        $sql = '';
        if (strlen($foreignKey->getName())) {
            $sql .= 'CONSTRAINT ' . $foreignKey->getQuotedName($this) . ' ';
        }
        $sql .= 'FOREIGN KEY (';

        if (count($foreignKey->getLocalColumns()) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'local' required.");
        }
        if (count($foreignKey->getForeignColumns()) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'foreign' required.");
        }
        if (strlen($foreignKey->getForeignTableName()) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'foreignTable' required.");
        }

        return $sql . implode(', ', $foreignKey->getQuotedLocalColumns($this))
            . ') REFERENCES '
            . $foreignKey->getQuotedForeignTableName($this) . ' ('
            . implode(', ', $foreignKey->getQuotedForeignColumns($this)) . ')';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the UNIQUE constraint
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @return string DBMS specific SQL code portion needed to set the UNIQUE constraint
     *                of a field declaration.
     */
    public function getUniqueFieldDeclarationSQL()
    {
        return 'UNIQUE';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset The name of the charset.
     *
     * @return string DBMS specific SQL code portion needed to set the CHARACTER SET
     *                of a field declaration.
     */
    public function getColumnCharsetDeclarationSQL($charset)
    {
        return '';
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set the COLLATION
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $collation The name of the collation.
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a field declaration.
     */
    public function getColumnCollationDeclarationSQL($collation)
    {
        return $this->supportsColumnCollation() ? 'COLLATE ' . $collation : '';
    }

    /**
     * Whether the platform prefers sequences for ID generation.
     * Subclasses should override this method to return TRUE if they prefer sequences.
     *
     * @return bool
     */
    public function prefersSequences()
    {
        return false;
    }

    /**
     * Whether the platform prefers identity columns (eg. autoincrement) for ID generation.
     * Subclasses should override this method to return TRUE if they prefer identity columns.
     *
     * @return bool
     */
    public function prefersIdentityColumns()
    {
        return false;
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
    public function convertBooleans($item)
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
     * @param mixed $item
     *
     * @return bool|null
     */
    public function convertFromBoolean($item)
    {
        return $item === null ? null: (bool) $item;
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
    public function convertBooleansToDatabaseValue($item)
    {
        return $this->convertBooleans($item);
    }

    /**
     * Returns the SQL specific for the platform to get the current date.
     *
     * @return string
     */
    public function getCurrentDateSQL()
    {
        return 'CURRENT_DATE';
    }

    /**
     * Returns the SQL specific for the platform to get the current time.
     *
     * @return string
     */
    public function getCurrentTimeSQL()
    {
        return 'CURRENT_TIME';
    }

    /**
     * Returns the SQL specific for the platform to get the current timestamp
     *
     * @return string
     */
    public function getCurrentTimestampSQL()
    {
        return 'CURRENT_TIMESTAMP';
    }

    /**
     * Returns the SQL for a given transaction isolation level Connection constant.
     *
     * @param int $level
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    protected function _getTransactionIsolationLevelSQL($level)
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
                throw new InvalidArgumentException('Invalid isolation level:' . $level);
        }
    }

    /**
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListDatabasesSQL()
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL statement for retrieving the namespaces defined in the database.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListNamespacesSQL()
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $database
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListSequencesSQL($database)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $table
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListTableConstraintsSQL($table)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string      $table
     * @param string|null $database
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListTablesSQL()
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListUsersSQL()
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to list all views of a database or user.
     *
     * @param string $database
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListViewsSQL($database)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the list of indexes for the current database.
     *
     * The current database parameter is optional but will always be passed
     * when using the SchemaManager API and is the database the given table is in.
     *
     * Attention: Some platforms only support currentDatabase when they
     * are connected with that database. Cross-database information schema
     * requests may be impossible.
     *
     * @param string $table
     * @param string $currentDatabase
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $table
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getListTableForeignKeysSQL($table)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $name
     * @param string $sql
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getCreateViewSQL($name, $sql)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $name
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDropViewSQL($name)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL snippet to drop an existing sequence.
     *
     * @param Sequence|string $sequence
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDropSequenceSQL($sequence)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param string $sequenceName
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to create a new database.
     *
     * @param string $database The name of the database that should be created.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getCreateDatabaseSQL($database)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Returns the SQL to set the transaction isolation level.
     *
     * @param int $level
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getSetTransactionIsolationSQL($level)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Obtains DBMS specific SQL to be used to create datetime fields in
     * statements like CREATE TABLE.
     *
     * @param mixed[] $fieldDeclaration
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Obtains DBMS specific SQL to be used to create datetime with timezone offset fields.
     *
     * @param mixed[] $fieldDeclaration
     *
     * @return string
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        return $this->getDateTimeTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * Obtains DBMS specific SQL to be used to create date fields in statements
     * like CREATE TABLE.
     *
     * @param mixed[] $fieldDeclaration
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Obtains DBMS specific SQL to be used to create time fields in statements
     * like CREATE TABLE.
     *
     * @param mixed[] $fieldDeclaration
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * @param mixed[] $fieldDeclaration
     *
     * @return string
     */
    public function getFloatDeclarationSQL(array $fieldDeclaration)
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
    public function getDefaultTransactionIsolationLevel()
    {
        return TransactionIsolationLevel::READ_COMMITTED;
    }

    /* supports*() methods */

    /**
     * Whether the platform supports sequences.
     *
     * @return bool
     */
    public function supportsSequences()
    {
        return false;
    }

    /**
     * Whether the platform supports identity columns.
     *
     * Identity columns are columns that receive an auto-generated value from the
     * database on insert of a row.
     *
     * @return bool
     */
    public function supportsIdentityColumns()
    {
        return false;
    }

    /**
     * Whether the platform emulates identity columns through sequences.
     *
     * Some platforms that do not support identity columns natively
     * but support sequences can emulate identity columns by using
     * sequences.
     *
     * @return bool
     */
    public function usesSequenceEmulatedIdentityColumns()
    {
        return false;
    }

    /**
     * Gets the sequence name prefix based on table information.
     *
     * @param string      $tableName
     * @param string|null $schemaName
     *
     * @return string
     */
    public function getSequencePrefix($tableName, $schemaName = null)
    {
        if (! $schemaName) {
            return $tableName;
        }

        // Prepend the schema name to the table name if there is one
        return ! $this->supportsSchemas() && $this->canEmulateSchemas()
            ? $schemaName . '__' . $tableName
            : $schemaName . '.' . $tableName;
    }

    /**
     * Returns the name of the sequence for a particular identity column in a particular table.
     *
     * @see    usesSequenceEmulatedIdentityColumns
     *
     * @param string $tableName  The name of the table to return the sequence name for.
     * @param string $columnName The name of the identity column in the table to return the sequence name for.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getIdentitySequenceName($tableName, $columnName)
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Whether the platform supports indexes.
     *
     * @return bool
     */
    public function supportsIndexes()
    {
        return true;
    }

    /**
     * Whether the platform supports partial indexes.
     *
     * @return bool
     */
    public function supportsPartialIndexes()
    {
        return false;
    }

    /**
     * Whether the platform supports indexes with column length definitions.
     */
    public function supportsColumnLengthIndexes() : bool
    {
        return false;
    }

    /**
     * Whether the platform supports altering tables.
     *
     * @return bool
     */
    public function supportsAlterTable()
    {
        return true;
    }

    /**
     * Whether the platform supports transactions.
     *
     * @return bool
     */
    public function supportsTransactions()
    {
        return true;
    }

    /**
     * Whether the platform supports savepoints.
     *
     * @return bool
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Whether the platform supports releasing savepoints.
     *
     * @return bool
     */
    public function supportsReleaseSavepoints()
    {
        return $this->supportsSavepoints();
    }

    /**
     * Whether the platform supports primary key constraints.
     *
     * @return bool
     */
    public function supportsPrimaryConstraints()
    {
        return true;
    }

    /**
     * Whether the platform supports foreign key constraints.
     *
     * @return bool
     */
    public function supportsForeignKeyConstraints()
    {
        return true;
    }

    /**
     * Whether this platform supports onUpdate in foreign key constraints.
     *
     * @return bool
     */
    public function supportsForeignKeyOnUpdate()
    {
        return $this->supportsForeignKeyConstraints();
    }

    /**
     * Whether the platform supports database schemas.
     *
     * @return bool
     */
    public function supportsSchemas()
    {
        return false;
    }

    /**
     * Whether this platform can emulate schemas.
     *
     * Platforms that either support or emulate schemas don't automatically
     * filter a schema for the namespaced elements in {@link
     * AbstractManager#createSchema}.
     *
     * @return bool
     */
    public function canEmulateSchemas()
    {
        return false;
    }

    /**
     * Returns the default schema name.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    public function getDefaultSchemaName()
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * Whether this platform supports create database.
     *
     * Some databases don't allow to create and drop databases at all or only with certain tools.
     *
     * @return bool
     */
    public function supportsCreateDropDatabase()
    {
        return true;
    }

    /**
     * Whether the platform supports getting the affected rows of a recent update/delete type query.
     *
     * @return bool
     */
    public function supportsGettingAffectedRows()
    {
        return true;
    }

    /**
     * Whether this platform support to add inline column comments as postfix.
     *
     * @return bool
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * Whether this platform support the proprietary syntax "COMMENT ON asset".
     *
     * @return bool
     */
    public function supportsCommentOnStatement()
    {
        return false;
    }

    /**
     * Does this platform have native guid type.
     *
     * @return bool
     */
    public function hasNativeGuidType()
    {
        return false;
    }

    /**
     * Does this platform have native JSON type.
     *
     * @return bool
     */
    public function hasNativeJsonType()
    {
        return false;
    }

    /**
     * @deprecated
     *
     * @todo Remove in 3.0
     */
    public function getIdentityColumnNullInsertSQL()
    {
        return '';
    }

    /**
     * Whether this platform supports views.
     *
     * @return bool
     */
    public function supportsViews()
    {
        return true;
    }

    /**
     * Does this platform support column collation?
     *
     * @return bool
     */
    public function supportsColumnCollation()
    {
        return false;
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime value of this platform.
     *
     * @return string The format string.
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored datetime with timezone value of this platform.
     *
     * @return string The format string.
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored date value of this platform.
     *
     * @return string The format string.
     */
    public function getDateFormatString()
    {
        return 'Y-m-d';
    }

    /**
     * Gets the format string, as accepted by the date() function, that describes
     * the format of a stored time value of this platform.
     *
     * @return string The format string.
     */
    public function getTimeFormatString()
    {
        return 'H:i:s';
    }

    /**
     * Adds an driver-specific LIMIT clause to the query.
     *
     * @throws DBALException
     */
    final public function modifyLimitQuery(string $query, ?int $limit, int $offset = 0) : string
    {
        if ($offset < 0) {
            throw new DBALException(sprintf(
                'Offset must be a positive integer or zero, %d given',
                $offset
            ));
        }

        if ($offset > 0 && ! $this->supportsLimitOffset()) {
            throw new DBALException(sprintf(
                'Platform %s does not support offset values in limit queries.',
                $this->getName()
            ));
        }

        return $this->doModifyLimitQuery($query, $limit, $offset);
    }

    /**
     * Adds an platform-specific LIMIT clause to the query.
     */
    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset) : string
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
     * Whether the database platform support offsets in modify limit clauses.
     *
     * @return bool
     */
    public function supportsLimitOffset()
    {
        return true;
    }

    /**
     * Gets the character casing of a column in an SQL result set of this platform.
     *
     * @param string $column The column name for which to get the correct character casing.
     *
     * @return string The column name in the character casing used in SQL result sets.
     */
    public function getSQLResultCasing($column)
    {
        return $column;
    }

    /**
     * Makes any fixes to a name of a schema element (table, sequence, ...) that are required
     * by restrictions of the platform, like a maximum length.
     *
     * @param string $schemaElementName
     *
     * @return string
     */
    public function fixSchemaElementName($schemaElementName)
    {
        return $schemaElementName;
    }

    /**
     * Maximum length of any given database identifier, like tables or column names.
     *
     * @return int
     */
    public function getMaxIdentifierLength()
    {
        return 63;
    }

    /**
     * Returns the insert SQL for an empty insert statement.
     *
     * @param string $tableName
     * @param string $identifierColumnName
     *
     * @return string
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (null)';
    }

    /**
     * Generates a Truncate Table SQL statement for a given table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign keys.
     *
     * @param string $tableName
     * @param bool   $cascade
     *
     * @return string
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE ' . $tableIdentifier->getQuotedName($this);
    }

    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     */
    public function getDummySelectSQL(string $expression = '1') : string
    {
        return sprintf('SELECT %s', $expression);
    }

    /**
     * Returns the SQL to create a new savepoint.
     *
     * @param string $savepoint
     *
     * @return string
     */
    public function createSavePoint($savepoint)
    {
        return 'SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to release a savepoint.
     *
     * @param string $savepoint
     *
     * @return string
     */
    public function releaseSavePoint($savepoint)
    {
        return 'RELEASE SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to rollback a savepoint.
     *
     * @param string $savepoint
     *
     * @return string
     */
    public function rollbackSavePoint($savepoint)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the keyword list instance of this platform.
     *
     * @return KeywordList
     *
     * @throws DBALException If no keyword list is specified.
     */
    final public function getReservedKeywordsList()
    {
        // Check for an existing instantiation of the keywords class.
        if ($this->_keywords) {
            return $this->_keywords;
        }

        $class    = $this->getReservedKeywordsClass();
        $keywords = new $class();
        if (! $keywords instanceof KeywordList) {
            throw NotSupported::new(__METHOD__);
        }

        // Store the instance so it doesn't need to be generated on every request.
        $this->_keywords = $keywords;

        return $keywords;
    }

    /**
     * Returns the class name of the reserved keywords list.
     *
     * @return string
     *
     * @throws DBALException If not supported on this platform.
     */
    protected function getReservedKeywordsClass()
    {
        throw NotSupported::new(__METHOD__);
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
    public function quoteStringLiteral($str)
    {
        $c = $this->getStringLiteralQuoteCharacter();

        return $c . str_replace($c, $c . $c, $str) . $c;
    }

    /**
     * Gets the character used for string literal quoting.
     *
     * @return string
     */
    public function getStringLiteralQuoteCharacter()
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
    final public function escapeStringForLike(string $inputString, string $escapeChar) : string
    {
        return preg_replace(
            '~([' . preg_quote($this->getLikeWildcardCharacters() . $escapeChar, '~') . '])~u',
            addcslashes($escapeChar, '\\') . '$1',
            $inputString
        );
    }

    protected function getLikeWildcardCharacters() : string
    {
        return '%_';
    }
}

<?php

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
use Doctrine\DBAL\Exception\InvalidLockMode;
use Doctrine\DBAL\LockMode;
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
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
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
use function func_get_arg;
use function func_get_args;
use function func_num_args;
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

    /** @var string[]|null */
    protected $doctrineTypeMapping;

    /**
     * Contains a list of all columns that should generate parseable column comments for type-detection
     * in reverse engineering scenarios.
     *
     * @deprecated This property is deprecated and will be removed in Doctrine DBAL 4.0.
     *
     * @var string[]|null
     */
    protected $doctrineTypeComments;

    /** @var EventManager|null */
    protected $_eventManager;

    /**
     * Holds the KeywordList instance for the current platform.
     *
     * @var KeywordList|null
     */
    protected $_keywords;

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
     * @param mixed[] $column
     */
    public function getAsciiStringTypeDeclarationSQL(array $column): string
    {
        return $this->getStringTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet used to declare a VARCHAR column type.
     *
     * @deprecated Use {@link getStringTypeDeclarationSQL()} instead.
     *
     * @param mixed[] $column
     */
    public function getVarcharTypeDeclarationSQL(array $column): string
    {
        if (! isset($column['length'])) {
            $column['length'] = $this->getVarcharDefaultLength();
        }

        $fixed = $column['fixed'] ?? false;

        $maxLength = $fixed
            ? $this->getCharMaxLength()
            : $this->getVarcharMaxLength();

        if ($column['length'] > $maxLength) {
            return $this->getClobTypeDeclarationSQL($column);
        }

        return $this->getVarcharTypeDeclarationSQLSnippet($column['length'], $fixed);
    }

    /**
     * Returns the SQL snippet used to declare a string column type.
     *
     * @param mixed[] $column
     */
    public function getStringTypeDeclarationSQL(array $column): string
    {
        return $this->getVarcharTypeDeclarationSQL($column);
    }

    /**
     * Returns the SQL snippet used to declare a BINARY/VARBINARY column type.
     *
     * @param mixed[] $column The column definition.
     */
    public function getBinaryTypeDeclarationSQL(array $column): string
    {
        if (! isset($column['length'])) {
            $column['length'] = $this->getBinaryDefaultLength();
        }

        $fixed = $column['fixed'] ?? false;

        $maxLength = $this->getBinaryMaxLength();

        if ($column['length'] > $maxLength) {
            if ($maxLength > 0) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/issues/3187',
                    'Binary column length %d is greater than supported by the platform (%d).'
                        . ' Reduce the column length or use a BLOB column instead.',
                    $column['length'],
                    $maxLength
                );
            }

            return $this->getBlobTypeDeclarationSQL($column);
        }

        return $this->getBinaryTypeDeclarationSQLSnippet($column['length'], $fixed);
    }

    /**
     * Returns the SQL snippet to declare a GUID/UUID column.
     *
     * By default this maps directly to a CHAR(36) and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param mixed[] $column
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
     * @param int|false $length
     * @param bool      $fixed
     *
     * @throws Exception If not supported on this platform.
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed): string
    {
        throw Exception::notSupported('VARCHARs not supported by Platform.');
    }

    /**
     * Returns the SQL snippet used to declare a BINARY/VARBINARY column type.
     *
     * @param int|false $length The length of the column.
     * @param bool      $fixed  Whether the column length is fixed.
     *
     * @throws Exception If not supported on this platform.
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed): string
    {
        throw Exception::notSupported('BINARY/VARBINARY column types are not supported by this platform.');
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
     * Gets the name of the platform.
     *
     * @deprecated Identify platforms by their class.
     */
    abstract public function getName(): string;

    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param string $dbType
     * @param string $doctrineType
     *
     * @throws Exception If the type is not found.
     */
    public function registerDoctrineTypeMapping($dbType, $doctrineType): void
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        if (! Types\Type::hasType($doctrineType)) {
            throw Exception::typeNotFound($doctrineType);
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
     * @throws Exception
     */
    public function getDoctrineTypeMapping($dbType): string
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if (! isset($this->doctrineTypeMapping[$dbType])) {
            throw new Exception(
                'Unknown database type ' . $dbType . ' requested, ' . static::class . ' may not support it.'
            );
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Checks if a database type is currently supported by this platform.
     *
     * @param string $dbType
     */
    public function hasDoctrineTypeMappingFor($dbType): bool
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
     * @deprecated This API will be removed in Doctrine DBAL 4.0.
     */
    protected function initializeCommentedDoctrineTypes(): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5058',
            '%s is deprecated and will be removed in Doctrine DBAL 4.0.',
            __METHOD__
        );

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
     * @deprecated Use {@link Type::requiresSQLCommentHint()} instead.
     */
    public function isCommentedDoctrineType(Type $doctrineType): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5058',
            '%s is deprecated and will be removed in Doctrine DBAL 4.0. Use Type::requiresSQLCommentHint() instead.',
            __METHOD__
        );

        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        return $doctrineType->requiresSQLCommentHint($this);
    }

    /**
     * Marks this type as to be commented in ALTER TABLE and CREATE TABLE statements.
     *
     * @param string|Type $doctrineType
     */
    public function markDoctrineTypeCommented($doctrineType): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5058',
            '%s is deprecated and will be removed in Doctrine DBAL 4.0. Use Type::requiresSQLCommentHint() instead.',
            __METHOD__
        );

        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        assert(is_array($this->doctrineTypeComments));

        $this->doctrineTypeComments[] = $doctrineType instanceof Type ? $doctrineType->getName() : $doctrineType;
    }

    /**
     * Gets the comment to append to a column comment that helps parsing this type in reverse engineering.
     *
     * @deprecated This method will be removed without replacement.
     */
    public function getDoctrineTypeComment(Type $doctrineType): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5107',
            '%s is deprecated and will be removed in Doctrine DBAL 4.0.',
            __METHOD__
        );

        return '(DC2Type:' . $doctrineType->getName() . ')';
    }

    /**
     * Gets the comment of a passed column modified by potential doctrine type comment hints.
     *
     * @deprecated This method will be removed without replacement.
     */
    protected function getColumnComment(Column $column): ?string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5107',
            '%s is deprecated and will be removed in Doctrine DBAL 4.0.',
            __METHOD__
        );

        $comment = $column->getComment();

        if ($column->getType()->requiresSQLCommentHint($this)) {
            $comment .= $this->getDoctrineTypeComment($column->getType());
        }

        return $comment;
    }

    /**
     * Gets the character used for identifier quoting.
     */
    public function getIdentifierQuoteCharacter(): string
    {
        return '"';
    }

    /**
     * Gets the string portion that starts an SQL comment.
     *
     * @deprecated
     */
    public function getSqlCommentStartString(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getSqlCommentStartString() is deprecated.'
        );

        return '--';
    }

    /**
     * Gets the string portion that ends an SQL comment.
     *
     * @deprecated
     */
    public function getSqlCommentEndString(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getSqlCommentEndString() is deprecated.'
        );

        return "\n";
    }

    /**
     * Gets the maximum length of a char column.
     *
     * @deprecated
     */
    public function getCharMaxLength(): int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3263',
            'AbstractPlatform::getCharMaxLength() is deprecated.'
        );

        return $this->getVarcharMaxLength();
    }

    /**
     * Gets the maximum length of a varchar column.
     *
     * @deprecated
     */
    public function getVarcharMaxLength(): int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3263',
            'AbstractPlatform::getVarcharMaxLength() is deprecated.'
        );

        return 4000;
    }

    /**
     * Gets the default length of a varchar column.
     *
     * @deprecated
     */
    public function getVarcharDefaultLength(): int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3263',
            'Relying on the default varchar column length is deprecated, specify the length explicitly.'
        );

        return 255;
    }

    /**
     * Gets the maximum length of a binary column.
     *
     * @deprecated
     */
    public function getBinaryMaxLength(): int
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3263',
            'AbstractPlatform::getBinaryMaxLength() is deprecated.'
        );

        return 4000;
    }

    /**
     * Gets the default length of a binary column.
     *
     * @deprecated
     */
    public function getBinaryDefaultLength(): int
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3263',
            'Relying on the default binary column length is deprecated, specify the length explicitly.'
        );

        return 255;
    }

    /**
     * Gets all SQL wildcard characters of the platform.
     *
     * @deprecated Use {@see AbstractPlatform::getLikeWildcardCharacters()} instead.
     *
     * @return string[]
     */
    public function getWildcards(): array
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getWildcards() is deprecated.'
            . ' Use AbstractPlatform::getLikeWildcardCharacters() instead.'
        );

        return ['%', '_'];
    }

    /**
     * Returns the regular expression operator.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getRegexpExpression(): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet to get the average value of a column.
     *
     * @deprecated Use AVG() in SQL instead.
     *
     * @param string $column The column to use.
     *
     * @return string Generated SQL including an AVG aggregate function.
     */
    public function getAvgExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getAvgExpression() is deprecated. Use AVG() in SQL instead.'
        );

        return 'AVG(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the number of rows (without a NULL value) of a column.
     *
     * If a '*' is used instead of a column the number of selected rows is returned.
     *
     * @deprecated Use COUNT() in SQL instead.
     *
     * @param string|int $column The column to use.
     *
     * @return string Generated SQL including a COUNT aggregate function.
     */
    public function getCountExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getCountExpression() is deprecated. Use COUNT() in SQL instead.'
        );

        return 'COUNT(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the highest value of a column.
     *
     * @deprecated Use MAX() in SQL instead.
     *
     * @param string $column The column to use.
     *
     * @return string Generated SQL including a MAX aggregate function.
     */
    public function getMaxExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getMaxExpression() is deprecated. Use MAX() in SQL instead.'
        );

        return 'MAX(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the lowest value of a column.
     *
     * @deprecated Use MIN() in SQL instead.
     *
     * @param string $column The column to use.
     *
     * @return string Generated SQL including a MIN aggregate function.
     */
    public function getMinExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getMinExpression() is deprecated. Use MIN() in SQL instead.'
        );

        return 'MIN(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the total sum of a column.
     *
     * @deprecated Use SUM() in SQL instead.
     *
     * @param string $column The column to use.
     *
     * @return string Generated SQL including a SUM aggregate function.
     */
    public function getSumExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getSumExpression() is deprecated. Use SUM() in SQL instead.'
        );

        return 'SUM(' . $column . ')';
    }

    // scalar functions

    /**
     * Returns the SQL snippet to get the md5 sum of a column.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @deprecated
     *
     * @param string $column
     */
    public function getMd5Expression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getMd5Expression() is deprecated.'
        );

        return 'MD5(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the length of a text column in characters.
     *
     * @param string $column
     */
    public function getLengthExpression($column): string
    {
        return 'LENGTH(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to get the squared value of a column.
     *
     * @deprecated Use SQRT() in SQL instead.
     *
     * @param string $column The column to use.
     *
     * @return string Generated SQL including an SQRT aggregate function.
     */
    public function getSqrtExpression($column): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getSqrtExpression() is deprecated. Use SQRT() in SQL instead.'
        );

        return 'SQRT(' . $column . ')';
    }

    /**
     * Returns the SQL snippet to round a numeric column to the number of decimals specified.
     *
     * @deprecated Use ROUND() in SQL instead.
     *
     * @param string     $column
     * @param string|int $decimals
     */
    public function getRoundExpression($column, $decimals = 0): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getRoundExpression() is deprecated. Use ROUND() in SQL instead.'
        );

        return 'ROUND(' . $column . ', ' . $decimals . ')';
    }

    /**
     * Returns the SQL snippet to get the remainder of the division operation $expression1 / $expression2.
     *
     * @param string $expression1
     * @param string $expression2
     */
    public function getModExpression($expression1, $expression2): string
    {
        return 'MOD(' . $expression1 . ', ' . $expression2 . ')';
    }

    /**
     * Returns the SQL snippet to trim a string.
     *
     * @param string      $str  The expression to apply the trim to.
     * @param int         $mode The position of the trim (leading/trailing/both).
     * @param string|bool $char The char to trim, has to be quoted already. Defaults to space.
     */
    public function getTrimExpression($str, $mode = TrimMode::UNSPECIFIED, $char = false): string
    {
        $expression = '';

        switch ($mode) {
            case TrimMode::LEADING:
                $expression = 'LEADING ';
                break;

            case TrimMode::TRAILING:
                $expression = 'TRAILING ';
                break;

            case TrimMode::BOTH:
                $expression = 'BOTH ';
                break;
        }

        if ($char !== false) {
            $expression .= $char . ' ';
        }

        if ($mode !== TrimMode::UNSPECIFIED || $char !== false) {
            $expression .= 'FROM ';
        }

        return 'TRIM(' . $expression . $str . ')';
    }

    /**
     * Returns the SQL snippet to trim trailing space characters from the expression.
     *
     * @deprecated Use RTRIM() in SQL instead.
     *
     * @param string $str Literal string or column name.
     */
    public function getRtrimExpression($str): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getRtrimExpression() is deprecated. Use RTRIM() in SQL instead.'
        );

        return 'RTRIM(' . $str . ')';
    }

    /**
     * Returns the SQL snippet to trim leading space characters from the expression.
     *
     * @deprecated Use LTRIM() in SQL instead.
     *
     * @param string $str Literal string or column name.
     */
    public function getLtrimExpression($str): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getLtrimExpression() is deprecated. Use LTRIM() in SQL instead.'
        );

        return 'LTRIM(' . $str . ')';
    }

    /**
     * Returns the SQL snippet to change all characters from the expression to uppercase,
     * according to the current character set mapping.
     *
     * @deprecated Use UPPER() in SQL instead.
     *
     * @param string $str Literal string or column name.
     */
    public function getUpperExpression($str): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getUpperExpression() is deprecated. Use UPPER() in SQL instead.'
        );

        return 'UPPER(' . $str . ')';
    }

    /**
     * Returns the SQL snippet to change all characters from the expression to lowercase,
     * according to the current character set mapping.
     *
     * @deprecated Use LOWER() in SQL instead.
     *
     * @param string $str Literal string or column name.
     */
    public function getLowerExpression($str): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getLowerExpression() is deprecated. Use LOWER() in SQL instead.'
        );

        return 'LOWER(' . $str . ')';
    }

    /**
     * Returns the SQL snippet to get the position of the first occurrence of substring $substr in string $str.
     *
     * @param string           $str      Literal string.
     * @param string           $substr   Literal string to find.
     * @param string|int|false $startPos Position to start at, beginning of string by default.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getLocateExpression($str, $substr, $startPos = false): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet to get the current system date.
     *
     * @deprecated Generate dates within the application.
     */
    public function getNowExpression(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4753',
            'AbstractPlatform::getNowExpression() is deprecated. Generate dates within the application.'
        );

        return 'NOW()';
    }

    /**
     * Returns a SQL snippet to get a substring inside an SQL statement.
     *
     * Note: Not SQL92, but common functionality.
     *
     * SQLite only supports the 2 parameter variant of this function.
     *
     * @param string          $string An sql string literal or column name/alias.
     * @param string|int      $start  Where to start the substring portion.
     * @param string|int|null $length The substring portion length.
     */
    public function getSubstringExpression($string, $start, $length = null): string
    {
        if ($length === null) {
            return 'SUBSTRING(' . $string . ' FROM ' . $start . ')';
        }

        return 'SUBSTRING(' . $string . ' FROM ' . $start . ' FOR ' . $length . ')';
    }

    /**
     * Returns a SQL snippet to concatenate the given expressions.
     *
     * Accepts an arbitrary number of string parameters. Each parameter must contain an expression.
     */
    public function getConcatExpression(): string
    {
        return implode(' || ', func_get_args());
    }

    /**
     * Returns the SQL for a logical not.
     *
     * Example:
     * <code>
     * $q = new Doctrine_Query();
     * $e = $q->expr;
     * $q->select('*')->from('table')
     *   ->where($e->eq('id', $e->not('null'));
     * </code>
     *
     * @deprecated Use NOT() in SQL instead.
     *
     * @param string $expression
     *
     * @return string The logical expression.
     */
    public function getNotExpression($expression): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getNotExpression() is deprecated. Use NOT() in SQL instead.'
        );

        return 'NOT(' . $expression . ')';
    }

    /**
     * Returns the SQL that checks if an expression is null.
     *
     * @deprecated Use IS NULL in SQL instead.
     *
     * @param string $expression The expression that should be compared to null.
     *
     * @return string The logical expression.
     */
    public function getIsNullExpression($expression): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getIsNullExpression() is deprecated. Use IS NULL in SQL instead.'
        );

        return $expression . ' IS NULL';
    }

    /**
     * Returns the SQL that checks if an expression is not null.
     *
     * @deprecated Use IS NOT NULL in SQL instead.
     *
     * @param string $expression The expression that should be compared to null.
     *
     * @return string The logical expression.
     */
    public function getIsNotNullExpression($expression): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getIsNotNullExpression() is deprecated. Use IS NOT NULL in SQL instead.'
        );

        return $expression . ' IS NOT NULL';
    }

    /**
     * Returns the SQL that checks if an expression evaluates to a value between two values.
     *
     * The parameter $expression is checked if it is between $value1 and $value2.
     *
     * Note: There is a slight difference in the way BETWEEN works on some databases.
     * http://www.w3schools.com/sql/sql_between.asp. If you want complete database
     * independence you should avoid using between().
     *
     * @deprecated Use BETWEEN in SQL instead.
     *
     * @param string $expression The value to compare to.
     * @param string $value1     The lower value to compare with.
     * @param string $value2     The higher value to compare with.
     *
     * @return string The logical expression.
     */
    public function getBetweenExpression($expression, $value1, $value2): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getBetweenExpression() is deprecated. Use BETWEEN in SQL instead.'
        );

        return $expression . ' BETWEEN ' . $value1 . ' AND ' . $value2;
    }

    /**
     * Returns the SQL to get the arccosine of a value.
     *
     * @deprecated Use ACOS() in SQL instead.
     *
     * @param string $value
     */
    public function getAcosExpression($value): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getAcosExpression() is deprecated. Use ACOS() in SQL instead.'
        );

        return 'ACOS(' . $value . ')';
    }

    /**
     * Returns the SQL to get the sine of a value.
     *
     * @deprecated Use SIN() in SQL instead.
     *
     * @param string $value
     */
    public function getSinExpression($value): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getSinExpression() is deprecated. Use SIN() in SQL instead.'
        );

        return 'SIN(' . $value . ')';
    }

    /**
     * Returns the SQL to get the PI value.
     *
     * @deprecated Use PI() in SQL instead.
     */
    public function getPiExpression(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getPiExpression() is deprecated. Use PI() in SQL instead.'
        );

        return 'PI()';
    }

    /**
     * Returns the SQL to get the cosine of a value.
     *
     * @deprecated Use COS() in SQL instead.
     *
     * @param string $value
     */
    public function getCosExpression($value): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getCosExpression() is deprecated. Use COS() in SQL instead.'
        );

        return 'COS(' . $value . ')';
    }

    /**
     * Returns the SQL to calculate the difference in days between the two passed dates.
     *
     * Computes diff = date1 - date2.
     *
     * @param string $date1
     * @param string $date2
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateDiffExpression($date1, $date2): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL to add the number of given seconds to a date.
     *
     * @param string $date
     * @param int    $seconds
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddSecondsExpression($date, $seconds): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to subtract the number of given seconds from a date.
     *
     * @param string $date
     * @param int    $seconds
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubSecondsExpression($date, $seconds): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $seconds, DateIntervalUnit::SECOND);
    }

    /**
     * Returns the SQL to add the number of given minutes to a date.
     *
     * @param string $date
     * @param int    $minutes
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddMinutesExpression($date, $minutes): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to subtract the number of given minutes from a date.
     *
     * @param string $date
     * @param int    $minutes
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubMinutesExpression($date, $minutes): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $minutes, DateIntervalUnit::MINUTE);
    }

    /**
     * Returns the SQL to add the number of given hours to a date.
     *
     * @param string $date
     * @param int    $hours
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddHourExpression($date, $hours): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to subtract the number of given hours to a date.
     *
     * @param string $date
     * @param int    $hours
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubHourExpression($date, $hours): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $hours, DateIntervalUnit::HOUR);
    }

    /**
     * Returns the SQL to add the number of given days to a date.
     *
     * @param string $date
     * @param int    $days
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddDaysExpression($date, $days): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to subtract the number of given days to a date.
     *
     * @param string $date
     * @param int    $days
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubDaysExpression($date, $days): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $days, DateIntervalUnit::DAY);
    }

    /**
     * Returns the SQL to add the number of given weeks to a date.
     *
     * @param string $date
     * @param int    $weeks
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddWeeksExpression($date, $weeks): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to subtract the number of given weeks from a date.
     *
     * @param string $date
     * @param int    $weeks
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubWeeksExpression($date, $weeks): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $weeks, DateIntervalUnit::WEEK);
    }

    /**
     * Returns the SQL to add the number of given months to a date.
     *
     * @param string $date
     * @param int    $months
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddMonthExpression($date, $months): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to subtract the number of given months to a date.
     *
     * @param string $date
     * @param int    $months
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubMonthExpression($date, $months): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $months, DateIntervalUnit::MONTH);
    }

    /**
     * Returns the SQL to add the number of given quarters to a date.
     *
     * @param string $date
     * @param int    $quarters
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddQuartersExpression($date, $quarters): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to subtract the number of given quarters from a date.
     *
     * @param string $date
     * @param int    $quarters
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubQuartersExpression($date, $quarters): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $quarters, DateIntervalUnit::QUARTER);
    }

    /**
     * Returns the SQL to add the number of given years to a date.
     *
     * @param string $date
     * @param int    $years
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateAddYearsExpression($date, $years): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '+', $years, DateIntervalUnit::YEAR);
    }

    /**
     * Returns the SQL to subtract the number of given years from a date.
     *
     * @param string $date
     * @param int    $years
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateSubYearsExpression($date, $years): string
    {
        return $this->getDateArithmeticIntervalExpression($date, '-', $years, DateIntervalUnit::YEAR);
    }

    /**
     * Returns the SQL for a date arithmetic expression.
     *
     * @param string $date     The column or literal representing a date to perform the arithmetic operation on.
     * @param string $operator The arithmetic operator (+ or -).
     * @param int    $interval The interval that shall be calculated into the date.
     * @param string $unit     The unit of the interval that shall be calculated into the date.
     *                         One of the DATE_INTERVAL_UNIT_* constants.
     *
     * @throws Exception If not supported on this platform.
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL bit AND comparison expression.
     *
     * @param string $value1
     * @param string $value2
     */
    public function getBitAndComparisonExpression($value1, $value2): string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * Returns the SQL bit OR comparison expression.
     *
     * @param string $value1
     * @param string $value2
     */
    public function getBitOrComparisonExpression($value1, $value2): string
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
     *
     * @param Table|string $table
     *
     * @throws InvalidArgumentException
     */
    public function getDropTableSQL($table): string
    {
        $tableArg = $table;

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        if (! is_string($table)) {
            throw new InvalidArgumentException(
                __METHOD__ . '() expects $table parameter to be string or ' . Table::class . '.'
            );
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
     */
    public function getDropTemporaryTableSQL($table): string
    {
        return $this->getDropTableSQL($table);
    }

    /**
     * Returns the SQL to drop an index from a table.
     *
     * @param Index|string      $index
     * @param Table|string|null $table
     *
     * @throws InvalidArgumentException
     */
    public function getDropIndexSQL($index, $table = null): string
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif (! is_string($index)) {
            throw new InvalidArgumentException(
                __METHOD__ . '() expects $index parameter to be string or ' . Index::class . '.'
            );
        }

        return 'DROP INDEX ' . $index;
    }

    /**
     * Returns the SQL to drop a constraint.
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     *
     * @param Constraint|string $constraint
     * @param Table|string      $table
     */
    public function getDropConstraintSQL($constraint, $table): string
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
     */
    public function getDropForeignKeySQL($foreignKey, $table): string
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
     * @param int $createFlags
     * @psalm-param int-mask-of<self::CREATE_*> $createFlags
     *
     * @return string[] The sequence of SQL statements.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES): array
    {
        if (! is_int($createFlags)) {
            throw new InvalidArgumentException(
                'Second argument of AbstractPlatform::getCreateTableSQL() has to be integer.'
            );
        }

        if (count($table->getColumns()) === 0) {
            throw Exception::noColumnsSpecifiedForTable($table->getName());
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
            if ($table->hasOption('comment')) {
                $sql[] = $this->getCommentOnTableSQL($tableName, $table->getOption('comment'));
            }

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

    protected function getCommentOnTableSQL(string $tableName, ?string $comment): string
    {
        $tableName = new Identifier($tableName);

        return sprintf(
            'COMMENT ON TABLE %s IS %s',
            $tableName->getQuotedName($this),
            $this->quoteStringLiteral((string) $comment)
        );
    }

    /**
     * @param string      $tableName
     * @param string      $columnName
     * @param string|null $comment
     */
    public function getCommentOnColumnSQL($tableName, $columnName, $comment): string
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
     * @throws Exception If not supported on this platform.
     */
    public function getInlineColumnCommentSQL($comment): string
    {
        if (! $this->supportsInlineColumnComments()) {
            throw Exception::notSupported(__METHOD__);
        }

        return 'COMMENT ' . $this->quoteStringLiteral($comment);
    }

    /**
     * Returns the SQL used to create a table.
     *
     * @param string    $name
     * @param mixed[][] $columns
     * @param mixed[]   $options
     *
     * @return string[]
     */
    protected function _getCreateTableSQL($name, array $columns, array $options = []): array
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($index, $definition);
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
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL to change a sequence on this platform.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet to drop an existing sequence.
     *
     * @param Sequence|string $sequence
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDropSequenceSQL($sequence): string
    {
        if (! $this->supportsSequences()) {
            throw Exception::notSupported(__METHOD__);
        }

        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * Returns the SQL to create a constraint on a table on this platform.
     *
     * @deprecated Use {@see getCreateIndexSQL()}, {@see getCreateForeignKeySQL()}
     *             or {@see getCreateUniqueConstraintSQL()} instead.
     *
     * @param Table|string $table
     *
     * @throws InvalidArgumentException
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table): string
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
        } elseif ($constraint instanceof UniqueConstraint) {
            $query .= ' UNIQUE';
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
     * @throws InvalidArgumentException
     */
    public function getCreateIndexSQL(Index $index, $table): string
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
     * @param Table|string $table
     */
    public function getCreatePrimaryKeySQL(Index $index, $table): string
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
     * @throws Exception If not supported on this platform.
     */
    public function getCreateSchemaSQL($schemaName): string
    {
        if (! $this->supportsSchemas()) {
            throw Exception::notSupported(__METHOD__);
        }

        return 'CREATE SCHEMA ' . $schemaName;
    }

    /**
     * Returns the SQL to create a unique constraint on a table on this platform.
     */
    public function getCreateUniqueConstraintSQL(UniqueConstraint $constraint, string $tableName): string
    {
        return $this->getCreateConstraintSQL($constraint, $tableName);
    }

    /**
     * Returns the SQL snippet to drop a schema.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDropSchemaSQL(string $schemaName): string
    {
        if (! $this->supportsSchemas()) {
            throw Exception::notSupported(__METHOD__);
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
     * @param string $str The identifier name to be quoted.
     *
     * @return string The quoted identifier string.
     */
    public function quoteIdentifier($str): string
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
    public function quoteSingleIdentifier($str): string
    {
        $c = $this->getIdentifierQuoteCharacter();

        return $c . str_replace($c, $c . $c, $str) . $c;
    }

    /**
     * Returns the SQL to create a new foreign key.
     *
     * @param ForeignKeyConstraint $foreignKey The foreign key constraint.
     * @param Table|string         $table      The name of the table on which the foreign key is to be created.
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table): string
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
     * @throws Exception If not supported on this platform.
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @param mixed[] $columnSql
     */
    protected function onSchemaAlterTableAddColumn(Column $column, TableDiff $diff, &$columnSql): bool
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
    protected function onSchemaAlterTableRemoveColumn(Column $column, TableDiff $diff, &$columnSql): bool
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
    protected function onSchemaAlterTableChangeColumn(ColumnDiff $columnDiff, TableDiff $diff, &$columnSql): bool
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
     */
    protected function onSchemaAlterTableRenameColumn($oldColumnName, Column $column, TableDiff $diff, &$columnSql): bool
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
     */
    protected function onSchemaAlterTable(TableDiff $diff, &$sql): bool
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
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff): array
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
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName): array
    {
        return [
            $this->getDropIndexSQL($oldIndexName, $tableName),
            $this->getCreateIndexSQL($index, $tableName),
        ];
    }

    /**
     * Gets declaration of a number of columns in bulk.
     *
     * @param mixed[][] $columns A multidimensional associative array.
     *                           The first dimension determines the column name, while the second
     *                           dimension is keyed with the name of the properties
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

        foreach ($columns as $name => $column) {
            $declarations[] = $this->getColumnDeclarationSQL($name, $column);
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
    public function getColumnDeclarationSQL($name, array $column): string
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

            $unique = ! empty($column['unique']) ?
                ' ' . $this->getUniqueFieldDeclarationSQL() : '';

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
    public function getDefaultValueDeclarationSQL($column): string
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
        foreach ($definition as $column => $def) {
            if (is_string($def)) {
                $constraints[] = 'CHECK (' . $def . ')';
            } else {
                if (isset($def['min'])) {
                    $constraints[] = 'CHECK (' . $column . ' >= ' . $def['min'] . ')';
                }

                if (isset($def['max'])) {
                    $constraints[] = 'CHECK (' . $column . ' <= ' . $def['max'] . ')';
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
    public function getUniqueConstraintDeclarationSQL($name, UniqueConstraint $constraint): string
    {
        $columns = $constraint->getQuotedColumns($this);
        $name    = new Identifier($name);

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        $constraintFlags = array_merge(['UNIQUE'], array_map('strtoupper', $constraint->getFlags()));
        $constraintName  = $name->getQuotedName($this);
        $columnListNames = $this->getColumnsFieldDeclarationListSQL($columns);

        return sprintf('CONSTRAINT %s %s (%s)', $constraintName, implode(' ', $constraintFlags), $columnListNames);
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
    public function getIndexDeclarationSQL($name, Index $index): string
    {
        $columns = $index->getColumns();
        $name    = new Identifier($name);

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name->getQuotedName($this)
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
     * @deprecated
     *
     * @return string The string required to be placed between "CREATE" and "TABLE"
     *                to generate a temporary table, if possible.
     */
    public function getTemporaryTableSQL(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getTemporaryTableSQL() is deprecated.'
        );

        return 'TEMPORARY';
    }

    /**
     * Some vendors require temporary table names to be qualified specially.
     *
     * @param string $tableName
     */
    public function getTemporaryTableName($tableName): string
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
    public function getForeignKeyReferentialActionSQL($action): string
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
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @throws InvalidArgumentException
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql = '';
        if (strlen($foreignKey->getName()) > 0) {
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
     * of a column declaration to be used in statements like CREATE TABLE.
     *
     * @deprecated Use UNIQUE in SQL instead.
     *
     * @return string DBMS specific SQL code portion needed to set the UNIQUE constraint
     *                of a column declaration.
     */
    public function getUniqueFieldDeclarationSQL(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getUniqueFieldDeclarationSQL() is deprecated. Use UNIQUE in SQL instead.'
        );

        return 'UNIQUE';
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
    public function getColumnCharsetDeclarationSQL($charset): string
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
    public function getColumnCollationDeclarationSQL($collation): string
    {
        return $this->supportsColumnCollation() ? 'COLLATE ' . $collation : '';
    }

    /**
     * Whether the platform prefers identity columns (eg. autoincrement) for ID generation.
     * Subclasses should override this method to return TRUE if they prefer identity columns.
     *
     * @deprecated
     */
    public function prefersIdentityColumns(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/1519',
            'AbstractPlatform::prefersIdentityColumns() is deprecated.'
        );

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
     */
    public function convertFromBoolean($item): ?bool
    {
        return $item === null ? null : (bool) $item;
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
     * @param int $level
     *
     * @throws InvalidArgumentException
     */
    protected function _getTransactionIsolationLevelSQL($level): string
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
     * @throws Exception If not supported on this platform.
     */
    public function getListDatabasesSQL(): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for retrieving the namespaces defined in the database.
     *
     * @deprecated Use {@see AbstractSchemaManager::listSchemaNames()} instead.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListNamespacesSQL(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4503',
            'AbstractPlatform::getListNamespacesSQL() is deprecated,'
                . ' use AbstractSchemaManager::listSchemaNames() instead.'
        );

        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @param string $database
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListSequencesSQL($database): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated
     *
     * @param string $table
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListTableConstraintsSQL($table): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     *
     * @param string $table
     * @param string $database
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListTableColumnsSQL($table, $database = null): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListTablesSQL(): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListUsersSQL(): string
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::getListUsersSQL() is deprecated.'
        );

        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL to list all views of a database or user.
     *
     * @param string $database
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListViewsSQL($database): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     *
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
     * @param string $database
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListTableIndexesSQL($table, $database = null): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @deprecated The SQL used for schema introspection is an implementation detail and should not be relied upon.
     *
     * @param string $table
     *
     * @throws Exception If not supported on this platform.
     */
    public function getListTableForeignKeysSQL($table): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * @param string $name
     * @param string $sql
     */
    public function getCreateViewSQL($name, $sql): string
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * @param string $name
     */
    public function getDropViewSQL($name): string
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * @param string $sequence
     *
     * @throws Exception If not supported on this platform.
     */
    public function getSequenceNextValSQL($sequence): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL to create a new database.
     *
     * @param string $name The name of the database that should be created.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getCreateDatabaseSQL($name): string
    {
        if (! $this->supportsCreateDropDatabase()) {
            throw Exception::notSupported(__METHOD__);
        }

        return 'CREATE DATABASE ' . $name;
    }

    /**
     * Returns the SQL snippet to drop an existing database.
     *
     * @param string $name The name of the database that should be dropped.
     */
    public function getDropDatabaseSQL($name): string
    {
        if (! $this->supportsCreateDropDatabase()) {
            throw Exception::notSupported(__METHOD__);
        }

        return 'DROP DATABASE ' . $name;
    }

    /**
     * Returns the SQL to set the transaction isolation level.
     *
     * @param int $level
     *
     * @throws Exception If not supported on this platform.
     */
    public function getSetTransactionIsolationSQL($level): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Obtains DBMS specific SQL to be used to create datetime columns in
     * statements like CREATE TABLE.
     *
     * @param mixed[] $column
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        throw Exception::notSupported(__METHOD__);
    }

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
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDateTypeDeclarationSQL(array $column): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Obtains DBMS specific SQL to be used to create time columns in statements
     * like CREATE TABLE.
     *
     * @param mixed[] $column
     *
     * @throws Exception If not supported on this platform.
     */
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        throw Exception::notSupported(__METHOD__);
    }

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
    public function getIdentitySequenceName($tableName, $columnName): string
    {
        throw Exception::notSupported(__METHOD__);
    }

    /**
     * Whether the platform supports indexes.
     *
     * @deprecated
     */
    public function supportsIndexes(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsIndexes() is deprecated.'
        );

        return true;
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
     * Whether the platform supports altering tables.
     *
     * @deprecated All platforms must implement altering tables.
     */
    public function supportsAlterTable(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsAlterTable() is deprecated. All platforms must implement altering tables.'
        );

        return true;
    }

    /**
     * Whether the platform supports transactions.
     *
     * @deprecated
     */
    public function supportsTransactions(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsTransactions() is deprecated.'
        );

        return true;
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
     * Whether the platform supports primary key constraints.
     *
     * @deprecated
     */
    public function supportsPrimaryConstraints(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsPrimaryConstraints() is deprecated.'
        );

        return true;
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
     * Whether this platform can emulate schemas.
     *
     * @deprecated
     *
     * Platforms that either support or emulate schemas don't automatically
     * filter a schema for the namespaced elements in {@see AbstractManager::createSchema()}.
     */
    public function canEmulateSchemas(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4805',
            'AbstractPlatform::canEmulateSchemas() is deprecated.'
        );

        return false;
    }

    /**
     * Returns the default schema name.
     *
     * @throws Exception If not supported on this platform.
     */
    public function getDefaultSchemaName(): string
    {
        throw Exception::notSupported(__METHOD__);
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
     * Whether the platform supports getting the affected rows of a recent update/delete type query.
     *
     * @deprecated
     */
    public function supportsGettingAffectedRows(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsGettingAffectedRows() is deprecated.'
        );

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
     * Whether this platform supports views.
     *
     * @deprecated All platforms must implement support for views.
     */
    public function supportsViews(): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsViews() is deprecated. All platforms must implement support for views.'
        );

        return true;
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
     * @param string   $query
     * @param int|null $limit
     * @param int      $offset
     *
     * @throws Exception
     */
    final public function modifyLimitQuery($query, $limit, $offset = 0): string
    {
        if ($offset < 0) {
            throw new Exception(sprintf(
                'Offset must be a positive integer or zero, %d given',
                $offset
            ));
        }

        if ($offset > 0 && ! $this->supportsLimitOffset()) {
            throw new Exception(sprintf(
                'Platform %s does not support offset values in limit queries.',
                $this->getName()
            ));
        }

        if ($limit !== null) {
            $limit = (int) $limit;
        }

        return $this->doModifyLimitQuery($query, $limit, (int) $offset);
    }

    /**
     * Adds an platform-specific LIMIT clause to the query.
     *
     * @param string   $query
     * @param int|null $limit
     * @param int      $offset
     */
    protected function doModifyLimitQuery($query, $limit, $offset): string
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
     * @deprecated All platforms must implement support for offsets in modify limit clauses.
     */
    public function supportsLimitOffset(): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pulls/4724',
            'AbstractPlatform::supportsViews() is deprecated.'
            . ' All platforms must implement support for offsets in modify limit clauses.'
        );

        return true;
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
     *
     * @param string $quotedTableName
     * @param string $quotedIdentifierColumnName
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $quotedIdentifierColumnName): string
    {
        return 'INSERT INTO ' . $quotedTableName . ' (' . $quotedIdentifierColumnName . ') VALUES (null)';
    }

    /**
     * Generates a Truncate Table SQL statement for a given table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign keys.
     *
     * @param string $tableName
     * @param bool   $cascade
     */
    public function getTruncateTableSQL($tableName, $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE ' . $tableIdentifier->getQuotedName($this);
    }

    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     */
    public function getDummySelectSQL(): string
    {
        $expression = func_num_args() > 0 ? func_get_arg(0) : '1';

        return sprintf('SELECT %s', $expression);
    }

    /**
     * Returns the SQL to create a new savepoint.
     *
     * @param string $savepoint
     */
    public function createSavePoint($savepoint): string
    {
        return 'SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to release a savepoint.
     *
     * @param string $savepoint
     */
    public function releaseSavePoint($savepoint): string
    {
        return 'RELEASE SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL to rollback a savepoint.
     *
     * @param string $savepoint
     */
    public function rollbackSavePoint($savepoint): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the keyword list instance of this platform.
     *
     * @throws Exception If no keyword list is specified.
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
     *
     * This method will become @abstract in DBAL 4.0.0.
     *
     * @throws Exception
     */
    protected function createReservedKeywordsList(): KeywordList
    {
        $class    = $this->getReservedKeywordsClass();
        $keywords = new $class();
        if (! $keywords instanceof KeywordList) {
            throw Exception::notSupported(__METHOD__);
        }

        return $keywords;
    }

    /**
     * Returns the class name of the reserved keywords list.
     *
     * @deprecated Implement {@see createReservedKeywordsList()} instead.
     *
     * @psalm-return class-string<KeywordList>
     *
     * @throws Exception If not supported on this platform.
     */
    protected function getReservedKeywordsClass(): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'AbstractPlatform::getReservedKeywordsClass() is deprecated,'
                . ' use AbstractPlatform::createReservedKeywordsList() instead.'
        );

        throw Exception::notSupported(__METHOD__);
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
    public function quoteStringLiteral($str): string
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
        return preg_replace(
            '~([' . preg_quote($this->getLikeWildcardCharacters() . $escapeChar, '~') . '])~u',
            addcslashes($escapeChar, '\\') . '$1',
            $inputString
        );
    }

    /**
     * @return array<string,mixed> An associative array with the name of the properties
     *                             of the column being declared as array indexes.
     */
    private function columnToArray(Column $column): array
    {
        $name = $column->getQuotedName($this);

        $columnData = array_merge($column->toArray(), [
            'name' => $name,
            'version' => $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false,
            'comment' => $this->getColumnComment($column),
        ]);

        if ($columnData['type'] instanceof Types\StringType && $columnData['length'] === null) {
            $columnData['length'] = $this->getVarcharDefaultLength();
        }

        return $columnData;
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

        if ($column1->getComment() !== $column2->getComment()) {
            return false;
        }

        return $column1->getType() === $column2->getType();
    }
}

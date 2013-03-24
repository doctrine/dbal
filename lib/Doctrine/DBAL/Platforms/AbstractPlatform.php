<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs;
use Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Event\SchemaDropTableEventArgs;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

/**
 * Base class for all database platforms. The database platforms are the central
 * point of abstraction of platform-specific behaviors, features and SQL dialects.
 * They are a passive source of information.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Lukas Smith <smith@pooteeweet.org>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @link   www.doctrine-project.org
 * @since  2.0
 *
 * @todo Remove any unnecessary methods.
 */
abstract class AbstractPlatform
{
    /**
     * @var integer
     */
    const CREATE_INDEXES = 1;

    /**
     * @var integer
     */
    const CREATE_FOREIGNKEYS = 2;

    /**
     * @var integer
     */
    const TRIM_UNSPECIFIED = 0;

    /**
     * @var integer
     */
    const TRIM_LEADING = 1;

    /**
     * @var integer
     */
    const TRIM_TRAILING = 2;

    /**
     * @var integer
     */
    const TRIM_BOTH = 3;

    /**
     * @var array|null
     */
    protected $doctrineTypeMapping = null;

    /**
     * Contains a list of all columns that should generate parsable column comments for type-detection
     * in reverse engineering scenarios.
     *
     * @var array|null
     */
    protected $doctrineTypeComments = null;

    /**
     * @var \Doctrine\Common\EventManager
     */
    protected $_eventManager;

    /**
     * Holds the reserved keywords list instance of the current platform.
     *
     * @var \Doctrine\DBAL\Platforms\Keywords\KeywordList
     */
    protected $_keywords;

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Sets the event manager used by this platform.
     *
     * @param \Doctrine\Common\EventManager $eventManager Event manager used by this platform.
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * Returns the event manager used by this platform.
     *
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Returns the SQL snippet for declaring a boolean column.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    abstract public function getBooleanTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet for declaring a 4 byte integer column.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    abstract public function getIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet for declaring an 8 byte integer column.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    abstract public function getBigIntTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet for declaring a 2 byte integer column.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $columnDef);

    /**
     * Returns the SQL snippet for declaring common properties of an integer column.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    abstract protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Lazy loads Doctrine type mappings.
     */
    abstract protected function initializeDoctrineTypeMappings();

    /**
     * Initializes Doctrine type mappings with the platform defaults
     * and with all additional type mappings.
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
     * Returns the SQL snippet for declaring a VARCHAR column.
     *
     * @param array $field Column definition.
     *
     * @return string
     */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if ( ! isset($field['length'])) {
            $field['length'] = $this->getVarcharDefaultLength();
        }

        $field['fixed'] = isset($field['fixed']) ? $field['fixed'] : false;

        if ($field['length'] > $this->getVarcharMaxLength()) {
            return $this->getClobTypeDeclarationSQL($field);
        }

        return $this->getVarcharTypeDeclarationSQLSnippet($field['length'], $field['fixed']);
    }

    /**
     * Returns the SQL snippet for declaring a GUID/UUID column.
     *
     * By default this maps directly to a VARCHAR and only maps to more
     * special data types when the underlying databases support this data type.
     *
     * @param array $field Column definition.
     *
     * @return string
     */
    public function getGuidTypeDeclarationSQL(array $field)
    {
        return $this->getVarcharTypeDeclarationSQL($field);
    }

    /**
     * Returns the SQL snippet for declaring a VARCHAR column.
     *
     * @param integer $length Column length.
     * @param boolean $fixed  Whether or not the column has a fixed length.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        throw DBALException::notSupported('VARCHARs not supported by Platform.');
    }

    /**
     * Returns the SQL snippet for declaring a CLOB column.
     *
     * @param array $field Column definition.
     *
     * @return string
     */
    abstract public function getClobTypeDeclarationSQL(array $field);

    /**
     * Returns the SQL snippet for declaring a BLOB column.
     *
     * @param array $field Column definition.
     *
     * @return string
     */
    abstract public function getBlobTypeDeclarationSQL(array $field);

    /**
     * Returns the name of this platform.
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Registers a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param string $dbType       Native database column type name.
     * @param string $doctrineType Doctrine column type name.
     *
     * @throws \Doctrine\DBAL\DBALException If the type is not found.
     */
    public function registerDoctrineTypeMapping($dbType, $doctrineType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        if ( ! Type::hasType($doctrineType)) {
            throw DBALException::typeNotFound($doctrineType);
        }

        $this->doctrineTypeMapping[strtolower($dbType)] = $doctrineType;
    }

    /**
     * Returns the Doctrine type that is mapped for the given database column type.
     *
     * @param string $dbType Native database column type name.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getDoctrineTypeMapping($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if ( ! isset($this->doctrineTypeMapping[$dbType])) {
            throw new DBALException(
                'Unknown database type ' . $dbType . ' requested, ' . get_class($this) . ' may not support it.'
            );
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Checks whether a given database column type is currently supported by this platform.
     *
     * @param string $dbType Native column type name.
     *
     * @return boolean True if supported by this platform, false otherwise.
     */
    public function hasDoctrineTypeMappingFor($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        return isset($this->doctrineTypeMapping[strtolower($dbType)]);
    }

    /**
     * Initializes the Doctrine type comments instance variable for in_array() checks.
     */
    protected function initializeCommentedDoctrineTypes()
    {
        $this->doctrineTypeComments = array();

        foreach (Type::getTypesMap() as $typeName => $className) {
            if (Type::getType($typeName)->requiresSQLCommentHint($this)) {
                $this->doctrineTypeComments[] = $typeName;
            }
        }
    }

    /**
     * Checks whether the platform needs to add a parseable type comment
     * to allow reverse engineering the given type.
     *
     * @param \Doctrine\DBAL\Types\Type $doctrineType Doctrine type.
     *
     * @return boolean True if Doctrine type needs comment, false otherwise.
     */
    public function isCommentedDoctrineType(Type $doctrineType)
    {
        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        return in_array($doctrineType->getName(), $this->doctrineTypeComments);
    }

    /**
     * Marks a type as to be commented in ALTER TABLE and CREATE TABLE statements.
     *
     * @param string|\Doctrine\DBAL\Types\Type $doctrineType Doctrine type.
     */
    public function markDoctrineTypeCommented($doctrineType)
    {
        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        $this->doctrineTypeComments[] = $doctrineType instanceof Type ? $doctrineType->getName() : $doctrineType;
    }

    /**
     * Returns the comment to append to a column comment that helps parsing a given type in reverse engineering.
     *
     * @param \Doctrine\DBAL\Types\Type $doctrineType Doctrine type.
     *
     * @return string
     */
    public function getDoctrineTypeComment(Type $doctrineType)
    {
        return '(DC2Type:' . $doctrineType->getName() . ')';
    }

    /**
     * Returns the comment of a passed column modified by potential doctrine type comment hints.
     *
     * @param \Doctrine\DBAL\Schema\Column $column Column.
     *
     * @return string
     */
    protected function getColumnComment(Column $column)
    {
        $comment = $column->getComment();
        $type = $column->getType();

        if ($this->isCommentedDoctrineType($type)) {
            $comment .= $this->getDoctrineTypeComment($type);
        }

        return $comment;
    }

    /**
     * Returns the character used for identifier quoting.
     *
     * @return string
     */
    public function getIdentifierQuoteCharacter()
    {
        return '"';
    }

    /**
     * Returns the SQL snippet for starting an SQL comment.
     *
     * @return string
     */
    public function getSqlCommentStartString()
    {
        return "--";
    }

    /**
     * Returns the SQL snippet for ending an SQL comment.
     *
     * @return string
     */
    public function getSqlCommentEndString()
    {
        return "\n";
    }

    /**
     * Returns the maximum length of a varchar field.
     *
     * @return integer
     */
    public function getVarcharMaxLength()
    {
        return 4000;
    }

    /**
     * Returns the default length of a varchar field.
     *
     * @return integer
     */
    public function getVarcharDefaultLength()
    {
        return 255;
    }

    /**
     * Returns all SQL wildcard characters of this platform.
     *
     * @return array
     */
    public function getWildcards()
    {
        return array('%', '_');
    }

    /**
     * Returns the SQL snippet for a regular expression operator.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getRegexpExpression()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a global unique identifier expression.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getGuidExpression()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for an expression to calculate an average value of a column.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getAvgExpression($column)
    {
        return 'AVG(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression
     * to count the number of rows (without a NULL value) of a column.
     *
     * If a '*' is used instead of a column the number of selected rows is returned.
     *
     * @param string|integer $column Column name to return the expression for.
     *
     * @return string
     */
    public function getCountExpression($column)
    {
        return 'COUNT(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to evaluate the highest value of a column.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getMaxExpression($column)
    {
        return 'MAX(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to evaluate the lowest value of a column.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getMinExpression($column)
    {
        return 'MIN(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to calculate the sum of a column.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getSumExpression($column)
    {
        return 'SUM(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to calculate the MD5 sum of a field.
     *
     * Note: Not SQL92, but common functionality.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getMd5Expression($column)
    {
        return 'MD5(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to evaluate the string length of a field.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getLengthExpression($column)
    {
        return 'LENGTH(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to calculate the squared value of a field.
     *
     * @param string $column Column name to return the expression for.
     *
     * @return string
     */
    public function getSqrtExpression($column)
    {
        return 'SQRT(' . $column . ')';
    }

    /**
     * Returns the SQL snippet for an expression to round a numeric field to the number of decimals specified.
     *
     * @param string  $column   Column name to return the expression for.
     * @param integer $decimals Number of decimals at which to round.
     *
     * @return string
     */
    public function getRoundExpression($column, $decimals = 0)
    {
        return 'ROUND(' . $column . ', ' . $decimals . ')';
    }

    /**
     * Returns the SQL snippet for an expression to calculate the remainder when one whole number is divided by another.
     *
     * $expression1 / $expression2.
     *
     * @param string $expression1 The dividend, or numerator of the division.
     * @param string $expression2 The divisor, or denominator of the division.
     *
     * @return string
     */
    public function getModExpression($expression1, $expression2)
    {
        return 'MOD(' . $expression1 . ', ' . $expression2 . ')';
    }

    /**
     * Returns the SQL snippet for an expression to trim a string,
     * leading/trailing/both and with a given char which defaults to space.
     *
     * @param string         $str  Literal string or column name to trim.
     * @param integer        $pos  Whether to trim leading, trailing or both characters.
     * @param string|boolean $char Character to trim (has to be quoted already).
     *
     * @return string
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        $posStr = '';
        $trimChar = $char != false ? $char . ' FROM ' : '';

        switch ($pos) {
            case self::TRIM_LEADING:
                $posStr = 'LEADING ' . $trimChar;
                break;
            case self::TRIM_TRAILING:
                $posStr = 'TRAILING ' . $trimChar;
                break;
            case self::TRIM_BOTH:
                $posStr = 'BOTH ' . $trimChar;
        }

        return 'TRIM(' . $posStr . $str . ')';
    }

    /**
     * Returns the SQL snippet for an expression to trim trailing spaces from a string.
     *
     * @param string $str Literal string or column name to trim.
     *
     * @return string
     */
    public function getRtrimExpression($str)
    {
        return 'RTRIM(' . $str . ')';
    }

    /**
     * Returns the SQL snippet for an expression to trim leading spaces from a string.
     *
     * @param string $str Literal string or column name to trim.
     *
     * @return string
     */
    public function getLtrimExpression($str)
    {
        return 'LTRIM(' . $str . ')';
    }

    /**
     * Returns the SQL snippet for an expression to change all characters of a string or column to uppercase
     * according to the current character set mapping.
     *
     * @param string $str Literal string or column name to change.
     *
     * @return string
     */
    public function getUpperExpression($str)
    {
        return 'UPPER(' . $str . ')';
    }

    /**
     * Returns the SQL snippet for an expression to change all characters of a string or column to lowercase
     * according to the current character set mapping.
     *
     * @param string $str Literal string or column name to change.
     *
     * @return string
     */
    public function getLowerExpression($str)
    {
        return 'LOWER(' . $str . ')';
    }

    /**
     * Returns the SQL snippet for an expression to locate the position of the first occurrence
     * of a substring in a string or column.
     *
     * @param string          $str      Literal string or column name.
     * @param string          $substr   Literal substring to find.
     * @param integer|boolean $startPos Position to start searching at (beginning of string by default).
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for an expression to get the current system date.
     *
     * @return string
     */
    public function getNowExpression()
    {
        return 'NOW()';
    }

    /**
     * Returns the SQL snippet for an expression to return a substring of a string.
     *
     * Note: Not SQL92, but common functionality.
     *
     * SQLite only supports the 2 parameter variant of this function.
     *
     * @param string       $value  Literal string or column name from which a substring is to be returned.
     * @param integer      $from   Start position of the substring to return, in characters.
     * @param integer|null $length Length of the substring to return, in characters. If length is specified,
     *                             the substring is restricted to that length.
     *
     * @return string
     */
    public function getSubstringExpression($value, $from, $length = null)
    {
        if ($length === null) {
            return 'SUBSTRING(' . $value . ' FROM ' . $from . ')';
        }

        return 'SUBSTRING(' . $value . ' FROM ' . $from . ' FOR ' . $length . ')';
    }

    /**
     * Returns the SQL snippet for an expression that concatenates
     * one or more strings and/or columns into one large string.
     *
     * Accepts an arbitrary number of parameters. Each parameter must contain an expression.
     *
     * @param string $expression,... Literal strings and/or columns that will be concatenated.
     *
     * @return string
     */
    public function getConcatExpression()
    {
        return join(' || ', func_get_args());
    }

    /**
     * Returns the SQL snippet for an expression that negates an expression (logical not).
     *
     * Example:
     * <code>
     * $q = new Doctrine_Query();
     * $e = $q->expr;
     * $q->select('*')->from('table')
     *   ->where($e->eq('id', $e->not('null'));
     * </code>
     *
     * @param string $expression Expression to negate.
     *
     * @return string
     */
    public function getNotExpression($expression)
    {
        return 'NOT(' . $expression . ')';
    }

    /**
     * Returns the SQL snippet for an expression that checks if a value is one in a set of given values.
     *
     * The first parameter must always specify the value that should be matched against.
     * Successive must contain a logical expression or an array with logical expressions.
     * These expressions will be matched against the first parameter.
     *
     * @param string       $column Column name to match against.
     * @param string|array $values Values that will be matched against the column.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getInExpression($column, $values)
    {
        $values = (array) $values;

        // TODO: fix this code: the method does not exist
        $values = $this->getIdentifiers($values);

        if (empty($values)) {
            throw new \InvalidArgumentException('Values must not be empty.');
        }

        return $column . ' IN (' . implode(', ', $values) . ')';
    }

    /**
     * Returns the SQL snippet for an expression that checks if an expression is null.
     *
     * @param string $expression Expression that should be checked to be null.
     *
     * @return string
     */
    public function getIsNullExpression($expression)
    {
        return $expression . ' IS NULL';
    }

    /**
     * Returns the SQL snippet for an expression that checks if an expression is not null.
     *
     * @param string $expression Expression that should be checked to be not null.
     *
     * @return string
     */
    public function getIsNotNullExpression($expression)
    {
        return $expression . ' IS NOT NULL';
    }

    /**
     * Returns the SQL snippet for a search condition expression to select a range of data between two values.
     *
     * Note: There is a slight difference in the way BETWEEN works on some databases.
     * http://www.w3schools.com/sql/sql_between.asp. If you want complete database
     * independence you should avoid using between().
     *
     * @param string $expression Expression to compare to.
     * @param string $value1     Lower value to compare with.
     * @param string $value2     Higher value to compare with.
     *
     * @return string
     */
    public function getBetweenExpression($expression, $value1, $value2)
    {
        return $expression . ' BETWEEN ' . $value1 . ' AND ' . $value2;
    }

    /**
     * Returns the SQL snippet for a mathematical function
     * to calculate the arc-cosine, in radians, of a numeric expression.
     *
     * @param string $value The cosine of the angle.
     *
     * @return string
     */
    public function getAcosExpression($value)
    {
        return 'ACOS(' . $value . ')';
    }

    /**
     * Returns the SQL snippet for a mathematical function
     * to calculate the sin of a numeric expression.
     *
     * @param string $value The angle, in radians.
     *
     * @return string
     */
    public function getSinExpression($value)
    {
        return 'SIN(' . $value . ')';
    }

    /**
     * Returns the SQL snippet for a mathematical function to calculate the numeric value PI.
     *
     * @return string
     */
    public function getPiExpression()
    {
        return 'PI()';
    }

    /**
     * Returns the SQL snippet for a mathematical function
     * to calculate the cosine of the angle in radians given by its argument.
     *
     * @param string $value The angle, in radians.
     *
     * @return string
     */
    public function getCosExpression($value)
    {
        return 'COS(' . $value . ')';
    }

    /**
     * Returns the SQL snippet for a date function
     * to calculate the number of days between two specified dates.
     *
     * Computes diff = date1 - date2.
     *
     * @param string $date1 The starting date for the interval. This value is subtracted from $date2.
     * @param string $date2 The ending date for the interval. $date1 is subtracted from this value.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateDiffExpression($date1, $date2)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to add a number of hours to a date.
     *
     * @param string  $date The date to add the number of hours to.
     * @param integer $hours The number of hours to add to the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateAddHourExpression($date, $hours)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to subtract a number of hours from a date.
     *
     * @param string  $date  The date to subtract the number of days from.
     * @param integer $hours The number of hours to subtract from the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateSubHourExpression($date, $hours)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to add a number of days to a date.
     *
     * @param string  $date The date to add the number of days to.
     * @param integer $days The number of days to add to the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateAddDaysExpression($date, $days)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to subtract a number of days from a date.
     *
     * @param string  $date The date to subtract the number of days from.
     * @param integer $days The number of days to subtract from the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateSubDaysExpression($date, $days)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to add a number of months to a date.
     *
     * @param string  $date   The date to add the number of months to.
     * @param integer $months The number of months to add to the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateAddMonthExpression($date, $months)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for a date function to subtract a number of months from a date.
     *
     * @param string  $date   The date to subtract the number of months from.
     * @param integer $months The number of months to subtract from the date.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateSubMonthExpression($date, $months)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for an expression to compare two bit values with a bitwise AND operator.
     *
     * @param string $value1 First expression for comparison.
     * @param string $value2 Second expression for comparison.
     *
     * @return string
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * Returns the SQL snippet for an expression to compare two bit values with a bitwise OR operator.
     *
     * @param string $value1 First expression for comparison.
     * @param string $value2 Second expression for comparison.
     *
     * @return string
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    /**
     * Returns the SQL snippet for declaring selected rows in an UPDATE/DELETE statement updateable/deletable.
     *
     * @return string
     */
    public function getForUpdateSQL()
    {
        return 'FOR UPDATE';
    }

    /**
     * Appends a table hint for locking rows to a SQL FROM clause.
     *
     * Honors that some SQL vendors such as Microsoft SQL Server use table hints for locking
     * instead of the ANSI SQL FOR UPDATE specification.
     *
     * @param string  $fromClause The SQL FROM clause to append the locking hint to.
     * @param integer $lockMode   Which lock mode to use as table hint.
     *
     * @return string
     */
    public function appendLockHint($fromClause, $lockMode)
    {
        return $fromClause;
    }

    /**
     * Returns the SQL snippet for acquiring a shared read lock on selected rows.
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
     * Returns the SQL snippet for acquiring an exclusive lock on selected rows.
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
     * Returns the SQL snippet for dropping an existing database.
     *
     * @param string $database Name of the database to drop.
     *
     * @return string
     */
    public function getDropDatabaseSQL($database)
    {
        return 'DROP DATABASE ' . $database;
    }

    /**
     * Returns the SQL statement for dropping an existing table.
     *
     * @param \Doctrine\DBAL\Schema\Table|string $table Table to drop.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDropTableSQL($table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        } elseif ( ! is_string($table)) {
            throw new \InvalidArgumentException(
                'getDropTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.'
            );
        }

        if (null !== $this->_eventManager && $this->_eventManager->hasListeners(Events::onSchemaDropTable)) {
            $eventArgs = new SchemaDropTableEventArgs($table, $this);
            $this->_eventManager->dispatchEvent(Events::onSchemaDropTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                return $eventArgs->getSql();
            }
        }

        return 'DROP TABLE ' . $table;
    }

    /**
     * Return the SQL to safely drop an existing temporary table WITHOUT implicitly committing an open transaction.
     *
     * @param \Doctrine\DBAL\Schema\Table|string $table Temporary table to drop.
     *
     * @return string
     */
    public function getDropTemporaryTableSQL($table)
    {
        return $this->getDropTableSQL($table);
    }

    /**
     * Returns the SQL statement for dropping an existing index from a table.
     *
     * @param \Doctrine\DBAL\Schema\Index|string $index Index to drop.
     * @param \Doctrine\DBAL\Schema\Table|string $table Table to drop the index from.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDropIndexSQL($index, $table = null)
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif ( ! is_string($index)) {
            throw new \InvalidArgumentException(
                'AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.'
            );
        }

        return 'DROP INDEX ' . $index;
    }

    /**
     * Returns the SQL statement for dropping an existing constraint from a table.
     *
     * @param \Doctrine\DBAL\Schema\Constraint|string $constraint Constraint to drop.
     * @param \Doctrine\DBAL\Schema\Table|string      $table      Table to drop constraint from.
     *
     * @return string
     */
    public function getDropConstraintSQL($constraint, $table)
    {
        if ($constraint instanceof Constraint) {
            $constraint = $constraint->getQuotedName($this);
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $constraint;
    }

    /**
     * Returns the SQL statement for dropping an existing foreign key constraint from a table.
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint|string $foreignKey Foreign key to drop.
     * @param \Doctrine\DBAL\Schema\Table|string                $table      Table to drop foreign key from.
     *
     * @return string
     */
    public function getDropForeignKeySQL($foreignKey, $table)
    {
        if ($foreignKey instanceof ForeignKeyConstraint) {
            $foreignKey = $foreignKey->getQuotedName($this);
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $foreignKey;
    }

    /**
     * Returns the sequence of SQL statements for creating a table
     * with the specified name, columns and constraints on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Table $table       The table to create.
     * @param integer                     $createFlags The table creation flags.
     *
     * @return array The sequence of SQL statements.
     *
     * @throws \InvalidArgumentException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        if ( ! is_int($createFlags)) {
            throw new \InvalidArgumentException("Second argument of AbstractPlatform::getCreateTableSQL() has to be integer.");
        }

        $tableColumns = $table->getColumns();

        if (empty($tableColumns)) {
            throw DBALException::noColumnsSpecifiedForTable($table->getName());
        }

        $tableName = $table->getQuotedName($this);
        $options = $table->getOptions();
        $options['uniqueConstraints'] = array();
        $options['indexes'] = array();
        $options['primary'] = array();

        if (($createFlags&self::CREATE_INDEXES) > 0) {
            foreach ($table->getIndexes() as $index) {
                /* @var \Doctrine\DBAL\Schema\Index $index */
                if ($index->isPrimary()) {
                    $options['primary']       = $index->getQuotedColumns($this);
                    $options['primary_index'] = $index;
                } else {
                    $options['indexes'][$index->getName()] = $index;
                }
            }
        }

        $columnSql = array();
        $columns = array();

        foreach ($tableColumns as $column) {
            /* @var \Doctrine\DBAL\Schema\Column $column */

            if (null !== $this->_eventManager && $this->_eventManager->hasListeners(Events::onSchemaCreateTableColumn)) {
                $eventArgs = new SchemaCreateTableColumnEventArgs($column, $table, $this);
                $this->_eventManager->dispatchEvent(Events::onSchemaCreateTableColumn, $eventArgs);

                $columnSql = array_merge($columnSql, $eventArgs->getSql());

                if ($eventArgs->isDefaultPrevented()) {
                    continue;
                }
            }

            $columnData = $column->toArray();
            $columnData['name'] = $column->getQuotedName($this);
            $columnData['version'] = $column->hasPlatformOption('version') ? $column->getPlatformOption('version') : false;
            $columnData['comment'] = $this->getColumnComment($column);

            if (strtolower($columnData['type']) == 'string' && $columnData['length'] === null) {
                $columnData['length'] = $this->getVarcharDefaultLength();
            }

            if (in_array($column->getName(), $options['primary'])) {
                $columnData['primary'] = true;
            }

            $columns[$columnData['name']] = $columnData;
        }

        if (($createFlags&self::CREATE_FOREIGNKEYS) > 0) {
            $options['foreignKeys'] = $table->getForeignKeys();
        }

        if (null !== $this->_eventManager && $this->_eventManager->hasListeners(Events::onSchemaCreateTable)) {
            $eventArgs = new SchemaCreateTableEventArgs($table, $columns, $options, $this);
            $this->_eventManager->dispatchEvent(Events::onSchemaCreateTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                return array_merge($eventArgs->getSql(), $columnSql);
            }
        }

        $sql = $this->_getCreateTableSQL($tableName, $columns, $options);

        if ($this->supportsCommentOnStatement()) {
            foreach ($tableColumns as $column) {
                if ($this->getColumnComment($column)) {
                    $sql[] = $this->getCommentOnColumnSQL($tableName, $column->getName(), $this->getColumnComment($column));
                }
            }
        }

        return array_merge($sql, $columnSql);
    }

    /**
     * Returns the SQL statement for declaring a column comment.
     *
     * @param string $tableName  The name of the table owning the column to declare the comment for.
     * @param string $columnName The column name to declare the comment for.
     * @param string $comment    The comment to declare on the column.
     *
     * @return string
     */
    public function getCommentOnColumnSQL($tableName, $columnName, $comment)
    {
        return "COMMENT ON COLUMN $tableName.$columnName IS '$comment'";
    }

    /**
     * Returns the sequence of SQL statements for creating a table.
     *
     * @param string $tableName The name of the table to create.
     * @param array  $columns   The table columns.
     * @param array  $options   The table options.
     *
     * @return array
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if ( ! empty($options['uniqueConstraints'])) {
            foreach ((array) $options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if ( ! empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY(' .
                implode(', ', array_unique(array_values((array) $options['primary']))) . ')';
        }

        if ( ! empty($options['indexes'])) {
            foreach ((array) $options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;
        $check = $this->getCheckDeclarationSQL($columns);

        if ( ! empty($check)) {
            $query .= ', ' . $check;
        }

        $query .= ')';

        $sql[] = $query;

        if ( ! empty($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }

    /**
     * Returns the SQL snippet for creating a temporary table.
     *
     * @return string
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE TEMPORARY TABLE';
    }

    /**
     * Returns the SQL statement for creating a sequence.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence Sequence to create.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for altering a sequence.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence Sequence to change.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for creating a constraint on a table.
     *
     * @param \Doctrine\DBAL\Schema\Constraint   $constraint Constraint to create on the table.
     * @param \Doctrine\DBAL\Schema\Table|string $table      Table to create the constraint on.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $query = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $constraint->getQuotedName($this);

        $columnList = '('. implode(', ', $constraint->getQuotedColumns($this)) . ')';

        $referencesClause = '';

        if ($constraint instanceof Index) {
            if ($constraint->isPrimary()) {
                $query .= ' PRIMARY KEY';
            } elseif ($constraint->isUnique()) {
                $query .= ' UNIQUE';
            } else {
                throw new \InvalidArgumentException(
                    'Can only create primary or unique constraints, no common indexes with getCreateConstraintSQL().'
                );
            }
        } elseif ($constraint instanceof ForeignKeyConstraint) {
            $query .= ' FOREIGN KEY';

            $referencesClause = ' REFERENCES ' . $constraint->getQuotedForeignTableName($this) .
                ' (' . implode(', ', $constraint->getQuotedForeignColumns($this)) . ')';
        }

        return $query . ' ('. implode(', ', (array) $constraint->getColumns()) . ')' . $referencesClause;
    }

    /**
     * Returns the SQL statement for creating an index on a table.
     *
     * @param \Doctrine\DBAL\Schema\Index        $index Index to create on the table.
     * @param \Doctrine\DBAL\Schema\Table|string $table Table to create the index on.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getCreateIndexSQL(Index $index, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $name = $index->getQuotedName($this);
        $columns = $index->getQuotedColumns($this);

        if (empty($columns)) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

       return
           'CREATE ' . $this->getCreateIndexSQLFlags($index) .
           'INDEX ' . $index->getQuotedName($this) .
           ' ON ' . $table .
           ' (' . $this->getIndexFieldDeclarationListSQL($columns) . ')';
    }

    /**
     * Returns the SQL snippet for adding additional flags to an index creation statement.
     *
     * @param \Doctrine\DBAL\Schema\Index $index Index to return flags for.
     *
     * @return string
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        return $index->isUnique() ? 'UNIQUE ' : '';
    }

    /**
     * Returns the SQL statement for creating an unnamed primary key constraint.
     *
     * @param \Doctrine\DBAL\Schema\Index        $index Primary key index to create.
     * @param \Doctrine\DBAL\Schema\Table|string $table Table to create the unnamed primary key constraint on.
     *
     * @return string
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . $this->getIndexFieldDeclarationListSQL($index->getColumns()) . ')';
    }

    /**
     * Returns the SQL to create a named schema.
     *
     * @param string $schemaName
     *
     * @return string
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getCreateSchemaSQL($schemaName)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Checks whether the schema $schemaName needs creating.
     *
     * @param string $schemaName
     *
     * @return boolean
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function schemaNeedsCreation($schemaName)
    {
        throw DBALException::notSupported(__METHOD__);
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
     * @param string $str Identifier name to be quoted.
     *
     * @return string Quoted identifier string.
     */
    public function quoteIdentifier($str)
    {
        if (strpos($str, ".") !== false) {
            return implode(".", array_map(array($this, "quoteIdentifier"), explode(".", $str)));
        }

        return $this->quoteSingleIdentifier($str);
    }

    /**
     * Quotes a single identifier (no dot chain separation).
     *
     * @param string $str Single identifier name to be quoted.
     *
     * @return string Quoted single identifier string.
     */
    public function quoteSingleIdentifier($str)
    {
        $char = $this->getIdentifierQuoteCharacter();

        return $char . str_replace($char, $char . $char, $str) . $char;
    }

    /**
     * Returns the SQL statement for creating a foreign key constraint on a table.
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey Foreign key to create on the table.
     * @param \Doctrine\DBAL\Schema\Table|string         $table      Table to create the foreign key constraint on.
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
     * Returns the sequence of SQL statements for altering an existing table.
     *
     * @param \Doctrine\DBAL\Schema\TableDiff $diff Diff of changes to perform on the table.
     *
     * @return array The sequence of SQL statements.
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Dispatches event for adding a column during a table alteration.
     *
     * @param \Doctrine\DBAL\Schema\Column    $column     Column to add during the table alteration.
     * @param \Doctrine\DBAL\Schema\TableDiff $diff       Diff of changes to perform on the table.
     * @param array                           &$columnSql Sequence of SQL statements for adding the column.
     *
     * @return boolean
     */
    protected function onSchemaAlterTableAddColumn(Column $column, TableDiff $diff, &$columnSql)
    {
        if (null === $this->_eventManager) {
            return false;
        }

        if ( ! $this->_eventManager->hasListeners(Events::onSchemaAlterTableAddColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableAddColumnEventArgs($column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableAddColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * Dispatches event for removing a column during a table alteration.
     *
     * @param \Doctrine\DBAL\Schema\Column    $column     Column to remove during the table alteration.
     * @param \Doctrine\DBAL\Schema\TableDiff $diff       Diff of changes to perform on the table.
     * @param array                           &$columnSql Sequence of SQL statements for removing the column.
     *
     * @return boolean
     */
    protected function onSchemaAlterTableRemoveColumn(Column $column, TableDiff $diff, &$columnSql)
    {
        if (null === $this->_eventManager) {
            return false;
        }

        if ( ! $this->_eventManager->hasListeners(Events::onSchemaAlterTableRemoveColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableRemoveColumnEventArgs($column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableRemoveColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * Dispatches event for changing a column during a table alteration.
     *
     * @param \Doctrine\DBAL\Schema\ColumnDiff $columnDiff Diff of changes to perform on the column.
     * @param \Doctrine\DBAL\Schema\TableDiff  $diff       Diff of changes to perform on the table.
     * @param array                            &$columnSql Sequence of SQL statements for changing the column.
     *
     * @return boolean
     */
    protected function onSchemaAlterTableChangeColumn(ColumnDiff $columnDiff, TableDiff $diff, &$columnSql)
    {
        if (null === $this->_eventManager) {
            return false;
        }

        if ( ! $this->_eventManager->hasListeners(Events::onSchemaAlterTableChangeColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableChangeColumnEventArgs($columnDiff, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableChangeColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * Dispatches event for renaming a column during a table alteration.
     *
     * @param string                          $oldColumnName Old/current name of the column.
     * @param \Doctrine\DBAL\Schema\Column    $column        Column to rename during the table alteration.
     * @param \Doctrine\DBAL\Schema\TableDiff $diff          Diff of changes to perform on the table.
     * @param array                           &$columnSql    Sequence of SQL statements for renaming the column.
     *
     * @return boolean
     */
    protected function onSchemaAlterTableRenameColumn($oldColumnName, Column $column, TableDiff $diff, &$columnSql)
    {
        if (null === $this->_eventManager) {
            return false;
        }

        if ( ! $this->_eventManager->hasListeners(Events::onSchemaAlterTableRenameColumn)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableRenameColumnEventArgs($oldColumnName, $column, $diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTableRenameColumn, $eventArgs);

        $columnSql = array_merge($columnSql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * Dispatches event for altering a table.
     *
     * @param \Doctrine\DBAL\Schema\TableDiff $diff Diff of changes to perform on the table.
     * @param array                           &$sql Sequence of SQL statements for altering the table.
     *
     * @return boolean
     */
    protected function onSchemaAlterTable(TableDiff $diff, &$sql)
    {
        if (null === $this->_eventManager) {
            return false;
        }

        if ( ! $this->_eventManager->hasListeners(Events::onSchemaAlterTable)) {
            return false;
        }

        $eventArgs = new SchemaAlterTableEventArgs($diff, $this);
        $this->_eventManager->dispatchEvent(Events::onSchemaAlterTable, $eventArgs);

        $sql = array_merge($sql, $eventArgs->getSql());

        return $eventArgs->isDefaultPrevented();
    }

    /**
     * Returns the sequence of SQL statements for removing indexes from a table,
     * either because existing indexes shall be removed from the table or existing indexes
     * shall be changed and have to be removed temporarily and recreated after table alteration again.
     *
     * @param \Doctrine\DBAL\Schema\TableDiff $diff Diff of changes to perform on the table.
     *
     * @return array The sequence of SQL statements for removing indexes from a table.
     */
    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $tableName = $diff->name;
        $sql = array();

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
     * Returns the sequence of SQL statements for creating indexes on a table,
     * either because new indexes shall be created on the table or existing indexes
     * shall be changed which had to be temporarily removed before table alteration.
     *
     * @param \Doctrine\DBAL\Schema\TableDiff $diff Diff of changes to perform on the table.
     *
     * @return array The sequence of SQL statements for creating indexes on a table.
     */
    protected function getPostAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        $tableName = false !== $diff->newName ? $diff->newName : $diff->name;
        $sql = array();

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

        return $sql;
    }

    /**
     * Returns the sequence of SQL statements for creating, changing and removing
     * indexes from a table during table alteration.
     *
     * @param \Doctrine\DBAL\Schema\TableDiff $diff Diff of changes to perform on the table.
     *
     * @return array The sequence of SQL statements for creating, changing and removing table indexes.
     */
    protected function _getAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        return array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $this->getPostAlterTableIndexForeignKeySQL($diff)
        );
    }

    /**
     * Returns the SQL snippet for listing a number of columns in bulk.
     *
     * @param array $fields A multidimensional associative array.
     *                      The first dimension determines the field name, while the second
     *                      dimension is keyed with the name of the properties
     *                      of the field being declared as array indexes. Currently, the types
     *                      of supported field properties are as follows:
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
        $queryFields = array();

        foreach ($fields as $fieldName => $field) {
            $queryFields[] = $this->getColumnDeclarationSQL($fieldName, $field);
        }

        return implode(', ', $queryFields);
    }

    /**
     * Returns the SQL snippet for declaring a column.
     *
     * @param string $name  Name of the column to be declared.
     * @param array  $field Associative array with the name of the properties
     *                      of the column being declared as array indexes. Currently, the types
     *                      of supported column properties are as follows:
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
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            if ( ! isset($field['type']) || ! $field['type'] instanceof Type) {
                throw new \InvalidArgumentException(
                    'Invalid column definition. "type" has to be and instance of \Doctrine\DBAL\Types\Type.'
                );
            }

            $columnDef =
                $field['type']->getSqlDeclaration($field, $this) . // column type declaration
                ( ! empty($field['charset']) ? ' ' . $this->getColumnCharsetDeclarationSQL($field['charset']) : '') . // column charset declaration
                $this->getDefaultValueDeclarationSQL($field) . // column default value declaration
                ( ! empty($field['notnull']) ? ' NOT NULL' : '') . // column not null declaration
                ( ! empty($field['unique']) ? ' ' . $this->getUniqueFieldDeclarationSQL() : '') . // column unique constraint declaration
                ( ! empty($field['check']) ? ' ' . $field['check'] : '') . // column check constraint declaration
                ( ! empty($field['collation']) ? ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : ''); // column collation declaration
        }

        if ($this->supportsInlineColumnComments() && ! empty($field['comment'])) {
            $columnDef .= " COMMENT '" . $field['comment'] . "'";
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * Returns the SQL snippet for declaring a floating point column of arbitrary precision.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    public function getDecimalTypeDeclarationSQL(array $columnDef)
    {
        $columnDef['precision'] = empty($columnDef['precision']) ? 10 : $columnDef['precision'];
        $columnDef['scale'] = empty($columnDef['scale']) ? 0 : $columnDef['scale'];

        return 'NUMERIC(' . $columnDef['precision'] . ', ' . $columnDef['scale'] . ')';
    }

    /**
     * Returns the SQL snippet for declaring a default value on a column.
     *
     * @param array $field Column definition.
     *
     * @return string
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        $default = empty($field['notnull']) ? 'NULL' : '';

        if (isset($field['default'])) {
            $default = "'" . $field['default'] . "'";

            if (isset($field['type'])) {
                switch ((string) $field['type']) {
                    case 'Integer':
                    case 'BigInteger':
                    case 'SmallInteger':
                        $default = $field['default'];
                        break;
                    case 'DateTime':
                        $currentTimestampSQL = $this->getCurrentTimestampSQL();

                        if ($field['default'] == $currentTimestampSQL) {
                            $default = $currentTimestampSQL;
                        }
                        break;
                    case 'Time':
                        $currentTimeSQL = $this->getCurrentTimeSQL();

                        if ($field['default'] == $currentTimeSQL) {
                            $default = $currentTimeSQL;
                        }
                        break;
                    case 'Date':
                        $currentDateSQL = $this->getCurrentDateSQL();

                        if ($field['default'] == $currentDateSQL) {
                            $default = $currentDateSQL;
                        }
                        break;
                    case 'Boolean':
                        $default = "'" . $this->convertBooleans($field['default']) . "'";
                }
            }
        }

        return $default ? ' DEFAULT ' . $default : '';
    }

    /**
     * Returns the SQL snippet for declaring a check constraint.
     *
     * @param array $definition Check constraint definition.
     *
     * @return string
     */
    public function getCheckDeclarationSQL(array $definition)
    {
        $constraints = array();

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
     * Returns the SQL snippet for declaring a unique constraint.
     *
     * @param string                      $name  Name of the unique constraint to be declared.
     * @param \Doctrine\DBAL\Schema\Index $index Index to be declared.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        $columns = $index->getQuotedColumns($this);

        if (empty($columns)) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return 'CONSTRAINT ' . $name . ' UNIQUE ('
             . $this->getIndexFieldDeclarationListSQL($columns)
             . ')';
    }

    /**
     * Returns the SQL snippet for declaring an index.
     *
     * @param string                      $name  Name of the index to be declared.
     * @param \Doctrine\DBAL\Schema\Index $index Index to be declared.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        $columns = $index->getQuotedColumns($this);

        if (empty($columns)) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ('
             . $this->getIndexFieldDeclarationListSQL($columns)
             . ')';
    }

    /**
     * Return the SQL snippet for a custom column declaration
     * e.g. when a column definition has a custom column declaration.
     * Only "AUTOINCREMENT" and "PRIMARY KEY" are added if appropriate.
     *
     * @param array $columnDef Column definition.
     *
     * @return string
     */
    public function getCustomTypeDeclarationSQL(array $columnDef)
    {
        return !empty($columnDef['columnDefinition']) ? $columnDef['columnDefinition'] : '';
    }

    /**
     * Returns the SQL snippet for listing a number of index columns in bulk.
     *
     * @param array $fields Column definitions.
     *
     * @return string
     */
    public function getIndexFieldDeclarationListSQL(array $fields)
    {
        $indexColumns = array();

        foreach ($fields as $field => $definition) {
            if (is_array($definition)) {
                $indexColumns[] = $field;
            } else {
                $indexColumns[] = $definition;
            }
        }

        return implode(', ', $indexColumns);
    }

    /**
     * Returns the SQL snippet for declaring a table temporary.
     *
     * Required for creating temporary tables with the CREATE ... TABLE statement.
     * The string returned by this method will be inserted between the CREATE ... TABLE clause.
     *
     * @return string
     */
    public function getTemporaryTableSQL()
    {
        return 'TEMPORARY';
    }

    /**
     * Returns the name for a temporary table.
     *
     * Some vendors require temporary table names to be qualified specially
     * e.g. #<TABLE_NAME>.
     *
     * @param string $tableName Table name to be qualified specially.
     *
     * @return string
     */
    public function getTemporaryTableName($tableName)
    {
        return $tableName;
    }

    /**
     * Returns the SQL snippet for declaring a foreign key constraint.
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey Foreign key constraint to be declared.
     *
     * @return string
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        return $this->getForeignKeyBaseDeclarationSQL($foreignKey) . $this->getAdvancedForeignKeyOptionsSQL($foreignKey);
    }

    /**
     * Returns the SQL snippet for declaring non-standard advanced foreign key options
     * like MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey Foreign key constraint to be declared.
     *
     * @return string
     */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey)
    {
        $sql = '';

        if ($this->supportsForeignKeyOnUpdate() && $foreignKey->hasOption('onUpdate')) {
            $sql .= ' ON UPDATE ' . $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onUpdate'));
        }

        if ($foreignKey->hasOption('onDelete')) {
            $sql .= ' ON DELETE ' . $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onDelete'));
        }

        return $sql;
    }

    /**
     * Returns the SQL snippet for declaring a referential action on a foreign key constraint.
     *
     * @param string $action Referential action to be declared on the foreign key constraint.
     *
     * @return string
     *
     * @throws \InvalidArgumentException if unknown referential action given
     */
    public function getForeignKeyReferentialActionSQL($action)
    {
        $action = strtoupper($action);

        switch ($action) {
            case 'CASCADE':
            case 'SET NULL':
            case 'NO ACTION':
            case 'RESTRICT':
            case 'SET DEFAULT':
                return $action;
            default:
                throw new \InvalidArgumentException('Invalid foreign key action: ' . $action);
        }
    }

    /**
     * Returns SQL snippet for the basic declaration of a foreign key constraint.
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey Foreign key to be declared.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        $sql = '';
        $foreignKeyName = $foreignKey->getName();
        $localColumns = $foreignKey->getLocalColumns();
        $foreignColumns = $foreignKey->getForeignColumns();
        $foreignTableName = $foreignKey->getForeignTableName();

        if ( ! empty($foreignKeyName)) {
            $sql .= 'CONSTRAINT ' . $foreignKey->getQuotedName($this) . ' ';
        }

        if (empty($localColumns)) {
            throw new \InvalidArgumentException("Incomplete definition. 'local' required.");
        }

        if (empty($foreignColumns)) {
            throw new \InvalidArgumentException("Incomplete definition. 'foreign' required.");
        }

        if (empty($foreignTableName)) {
            throw new \InvalidArgumentException("Incomplete definition. 'foreignTable' required.");
        }

        $sql .= implode(', ', $foreignKey->getQuotedLocalColumns($this))
              . ') REFERENCES '
              . $foreignKey->getQuotedForeignTableName($this) . ' ('
              . implode(', ', $foreignKey->getQuotedForeignColumns($this)) . ')';

        return $sql;
    }

    /**
     * Returns the SQL snippet for declaring a unique column.
     *
     * @return string
     */
    public function getUniqueFieldDeclarationSQL()
    {
        return 'UNIQUE';
    }

    /**
     * Returns the SQL snippet for declaring a character set on a column.
     *
     * @param string $charset Name of the character set to be declared on the column.
     *
     * @return string
     */
    public function getColumnCharsetDeclarationSQL($charset)
    {
        return '';
    }

    /**
     * Returns the SQL snippet for declaring a collation on a column.
     *
     * @param string $collation Name of the collation to be declared on the column.
     *
     * @return string
     */
    public function getColumnCollationDeclarationSQL($collation)
    {
        return '';
    }

    /**
     * Returns whether or not the platform prefers sequences over identity columns for ID generation.
     *
     * @return boolean
     */
    public function prefersSequences()
    {
        return false;
    }

    /**
     * Returns whether or not the platform prefers identity columns (e.g. autoincrement)
     * over sequences for ID generation.
     *
     * @return boolean
     */
    public function prefersIdentityColumns()
    {
        return false;
    }

    /**
     * Converts PHP boolean values to platform specific values.
     *
     * Some platforms need the boolean values to be converted.
     * The default conversion in this implementation converts to integers (false => 0, true => 1).
     *
     * @param mixed $item Value(s) to be converted to the platform specific value(s).
     *
     * @return mixed
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value)) {
                    $item[$key] = (int) $value;
                }
            }
        } elseif (is_bool($item)) {
            $item = (int) $item;
        }

        return $item;
    }

    /**
     * Returns the SQL snippet for retrieving the current date.
     *
     * @return string
     */
    public function getCurrentDateSQL()
    {
        return 'CURRENT_DATE';
    }

    /**
     * Returns the SQL snippet for retrieving the current time.
     *
     * @return string
     */
    public function getCurrentTimeSQL()
    {
        return 'CURRENT_TIME';
    }

    /**
     * Returns the SQL snippet for retrieving the current timestamp.
     *
     * @return string
     */
    public function getCurrentTimestampSQL()
    {
        return 'CURRENT_TIMESTAMP';
    }

    /**
     * Returns the SQL snippet for a transaction isolation level according to the given internal constant.
     *
     * @param integer $level Internal constant to return the transaction isolation level SQL snippet for.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function _getTransactionIsolationLevelSQL($level)
    {
        switch ($level) {
            case Connection::TRANSACTION_READ_UNCOMMITTED:
                return 'READ UNCOMMITTED';
            case Connection::TRANSACTION_READ_COMMITTED:
                return 'READ COMMITTED';
            case Connection::TRANSACTION_REPEATABLE_READ:
                return 'REPEATABLE READ';
            case Connection::TRANSACTION_SERIALIZABLE:
                return 'SERIALIZABLE';
            default:
                throw new \InvalidArgumentException('Invalid isolation level:' . $level);
        }
    }

    /**
     * Returns the SQL statement for listing all databases.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListDatabasesSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all sequences of a database.
     *
     * @param string $database Database name to list all sequences for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListSequencesSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all constraints of a table.
     *
     * @param string $table Table name to list all constraints for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListTableConstraintsSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all columns of a table.
     *
     * @param string $table    Table name to list all columns for.
     * @param string $database Database name of the table to list all columns for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all tables of a database.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListTablesSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all users.
     *
     * @return string.
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListUsersSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all views of a database.
     *
     * @param string $database Database name to list all views for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListViewsSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all indexes of a table.
     *
     * Attention: Some platforms only support currentDatabase when they
     * are connected with that database. Cross-database information schema
     * requests may be impossible.
     *
     * @param string $table           Table name to list all indexes for.
     * @param string $currentDatabase Name of the currently used database.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for listing all foreign key constraints of a table.
     *
     * @param string $table Table name to list all foreign key constraints for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getListTableForeignKeysSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for creating a view.
     *
     * @param string $name Name of the view to create.
     * @param string $sql  SQL SELECT statement of the view to create.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getCreateViewSQL($name, $sql)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for dropping an existing view.
     *
     * @param string $name Name of the view to drop.
     *
     * return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDropViewSQL($name)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for dropping an existing sequence.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence Sequence to drop.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDropSequenceSQL($sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for accessing the next value of a sequence.
     *
     * @param string $sequenceName Name of the sequence to access the next value for.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for creating a database.
     *
     * @param string $database Name of the database to create.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getCreateDatabaseSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL statement for setting the transaction isolation level.
     *
     * @param integer $level Internal constant that maps to the transaction isolation level to set.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getSetTransactionIsolationSQL($level)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for declaring a datetime column.
     *
     * @param array $fieldDeclaration Column definition.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for declaring a datetime with timezone offset column.
     *
     * @param array $fieldDeclaration Column definition.
     *
     * @return string
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        return $this->getDateTimeTypeDeclarationSQL($fieldDeclaration);
    }


    /**
     * Returns the SQL snippet for declaring a date column.
     *
     * @param array $fieldDeclaration Column definition.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for declaring a time column.
     *
     * @param array $fieldDeclaration Column definition.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the SQL snippet for declaring a floating point column.
     *
     * @param array $fieldDeclaration Column definition.
     *
     * @return string
     */
    public function getFloatDeclarationSQL(array $fieldDeclaration)
    {
        return 'DOUBLE PRECISION';
    }

    /**
     * Returns the default transaction isolation level of the platform.
     *
     * @see Doctrine\DBAL\Connection\TRANSACTION_* constants.
     *
     * @return integer The default isolation level of the platform.
     */
    public function getDefaultTransactionIsolationLevel()
    {
        return Connection::TRANSACTION_READ_COMMITTED;
    }

    /**
     * Checks wether the platform supports sequences.
     *
     * @return boolean True if the platform supports sequences, false otherwise.
     */
    public function supportsSequences()
    {
        return false;
    }

    /**
     * Checks whether the platform supports identity columns.
     *
     * Identity columns are columns that receive an auto-generated value from the
     * database on insert of a row.
     *
     * @return boolean True if the platform supports identity columns, false otherwise.
     */
    public function supportsIdentityColumns()
    {
        return false;
    }

    /**
     * Checks whether the platform supports indexes.
     *
     * @return boolean True if the platform supports indexes, false otherwise.
     */
    public function supportsIndexes()
    {
        return true;
    }

    /**
     * Checks whether the platform supports table alteration.
     *
     * @return boolean True if the platform supports table alteration, false otherwise.
     */
    public function supportsAlterTable()
    {
        return true;
    }

    /**
     * Checks whether the platform supports transactions.
     *
     * @return boolean True if the platform supports transactions, false otherwise.
     */
    public function supportsTransactions()
    {
        return true;
    }

    /**
     * Checks whether the platform supports transaction save points.
     *
     * @return boolean True if the platform supports transaction save points, false otherwise.
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Checks whether the platform supports releasing transaction save points.
     *
     * @return boolean True if the platform supports releasing transaction save points, false otherwise.
     */
    public function supportsReleaseSavepoints()
    {
        return $this->supportsSavepoints();
    }

    /**
     * Checks whether the platform supports primary key constraints.
     *
     * @return boolean True if the platform supports primary key constraints, false otherwise.
     */
    public function supportsPrimaryConstraints()
    {
        return true;
    }

    /**
     * Checks whether the platform supports foreign key constraints.
     *
     * @return boolean True if the platform supports foreign key constraints, false otherwise.
     */
    public function supportsForeignKeyConstraints()
    {
        return true;
    }

    /**
     * Returns whether or not the platform supports referential UPDATE action on foreign key constraints.
     *
     * @return boolean
     */
    public function supportsForeignKeyOnUpdate()
    {
        return $this->supportsForeignKeyConstraints();
    }

    /**
     * Returns whether or not the platform supports database schemas.
     *
     * @return boolean
     */
    public function supportsSchemas()
    {
        return false;
    }

    /**
     * Checks whether the platform supports emulating database schemas.
     *
     * Platforms that either support or emulate schemas don't automatically
     * filter a schema for the namespaced elements in
     * {@link AbstractManager#createSchema}.
     *
     * @return boolean True if the platform supports emulating database schemas, false otherwise.
     */
    public function canEmulateSchemas()
    {
        return false;
    }

    /**
     * Checks whether the platform supports creating and dropping databases.
     *
     * Some databases don't allow to create and drop databases at all or only with certain tools.
     *
     * @return boolean True if the platform supports creating and dropping databases, false otherwise.
     */
    public function supportsCreateDropDatabase()
    {
        return true;
    }

    /**
     * Checks whether the platform supports retrieving the affected rows of a recent UPDATE/DELETE type query.
     *
     * @return boolean True if the platform supports retrieving affected rows, false otherwise.
     */
    public function supportsGettingAffectedRows()
    {
        return true;
    }

    /**
     * Checks whether the platform supports inline column comments as postfix.
     *
     * @return boolean True if the platform supports inline column comments as postfix, false otherwise.
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * Checks whether the platform supports the proprietary "COMMENT ON asset" syntax.
     *
     * @return boolean True if the platform supports the proprietary "COMMENT ON asset" syntax, false otherwise.
     */
    public function supportsCommentOnStatement()
    {
        return false;
    }

    /**
     * Checks whether the platform has a native GUID column type.
     *
     * @return boolean True if the platform has a native GUID column type, false otherwise.
     */
    public function hasNativeGuidType()
    {
        return false;
    }

    /**
     * Returns the SQL statement for inserting a row with NULL values for the identity columns of a table.
     *
     * @return string
     *
     * @deprecated
     * @todo Remove in 3.0
     */
    public function getIdentityColumnNullInsertSQL()
    {
        return "";
    }

    /**
     * Checks whether the platform supports views.
     *
     * @return boolean True if the platform supports views, false otherwise.
     */
    public function supportsViews()
    {
        return true;
    }

    /**
     * Returns the format string, as accepted by the DATE() function, that describes
     * the format of a stored datetime value of this platform.
     *
     * @return string
     */
    public function getDateTimeFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Returns the format string, as accepted by the DATE() function, that describes
     * the format of a stored datetime with timezone value of this platform.
     *
     * @return string
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Returns the format string, as accepted by the DATE() function, that describes
     * the format of a stored date value of this platform.
     *
     * @return string
     */
    public function getDateFormatString()
    {
        return 'Y-m-d';
    }

    /**
     * Returns the format string, as accepted by the DATE() function, that describes
     * the format of a stored time value of this platform.
     *
     * @return string
     */
    public function getTimeFormatString()
    {
        return 'H:i:s';
    }

    /**
     * Modifies a query to return a limited number of results.
     *
     * @param string       $query  Query to limit the number of results of.
     * @param integer|null $limit  Number of results to limit.
     * @param integer|null $offset Offset to start returning the results at.
     *
     * @see doModifyLimitQuery
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    final public function modifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit !== null) {
            $limit = (int) $limit;
        }

        if ($offset !== null) {
            $offset = (int) $offset;

            if ($offset < 0) {
                throw new DBALException("LIMIT argument offset=$offset is not valid");
            }

            if ($offset > 0 && ! $this->supportsLimitOffset()) {
                throw new DBALException(sprintf(
                    'Platform %s does not support offset values in limit queries.',
                    $this->getName()
                ));
            }
        }

        return $this->doModifyLimitQuery($query, $limit, $offset);
    }

    /**
     * Adds a platform specific clause to the query to return a limited number of results.
     *
     * @param string  $query       Query to limit the number of results of.
     * @param integer $limit|null  Number of results to limit.
     * @param integer $offset|null Offset to start returning the results at.
     *
     * @return string
     */
    protected function doModifyLimitQuery($query, $limit, $offset)
    {
        if ($limit !== null) {
            $query .= ' LIMIT ' . $limit;
        }

        if ($offset !== null) {
            $query .= ' OFFSET ' . $offset;
        }

        return $query;
    }

    /**
     * Checks whether the platform supports offsets in limit clauses.
     *
     * @return boolean True if the platform supports offsets in limit clauses, false otherwise.
     */
    public function supportsLimitOffset()
    {
        return true;
    }

    /**
     * Returns the character casing of a column in a SQL result set of this platform.
     *
     * @param string $column Column name to get the correct character casing for.
     *
     * @return string Column name in the character casing used in SQL result sets.
     */
    public function getSQLResultCasing($column)
    {
        return $column;
    }

    /**
     * Makes any fixes to a name of a schema element (table, sequence, ...) that are required
     * by restrictions of the platform, like a maximum length.
     *
     * @param string $schemaElementName Name of the schema element to fix.
     *
     * @return string
     */
    public function fixSchemaElementName($schemaElementName)
    {
        return $schemaElementName;
    }

    /**
     * Returns the maximum length of a database identifier, like table or column names.
     *
     * @return integer
     */
    public function getMaxIdentifierLength()
    {
        return 63;
    }

    /**
     * Returns the SQL statement for inserting an empty row.
     *
     * @param string $tableName            Name of the table to insert the empty row into.
     * @param string $identifierColumnName Identifier column name of the table to insert the empty row into.
     *
     * @return string
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (null)';
    }

    /**
     * Returns the SQL statement for truncating a table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign key constraints.
     *
     * @param string  $tableName Name of the table to truncate.
     * @param boolean $cascade   Whether or not to cascade the truncation of the table
     *                           following the foreign key constraints.
     *
     * @return string
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE ' . $tableName;
    }

    /**
     * Returns the SQL statement for performing a dummy select.
     *
     * This is for test reasons, many vendors have special requirements for dummy statements.
     *
     * @return string
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1';
    }

    /**
     * Returns the SQL statement for creating a transactional save point.
     *
     * @param string $savepoint Name of the transactional save point to create.
     *
     * @return string
     */
    public function createSavePoint($savepoint)
    {
        return 'SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL statement for releasing a transactional save point.
     *
     * @param string $savepoint Name of the transactional save point to release.
     *
     * @return string
     */
    public function releaseSavePoint($savepoint)
    {
        return 'RELEASE SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the SQL statement for rolling back to a transactional save point.
     *
     * @param string $savepoint Name of the transactional save point to roll back to.
     *
     * @return string
     */
    public function rollbackSavePoint($savepoint)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $savepoint;
    }

    /**
     * Returns the reserved keywords list instance of this platform.
     *
     * @return \Doctrine\DBAL\Platforms\Keywords\KeywordList
     *
     * @throws \Doctrine\DBAL\DBALException If no keyword list is specified.
     */
    final public function getReservedKeywordsList()
    {
        // Check for an existing instantiation of the keywords class.
        if ($this->_keywords) {
            return $this->_keywords;
        }

        $class = $this->getReservedKeywordsClass();
        $keywords = new $class;

        if ( ! $keywords instanceof KeywordList) {
            throw DBALException::notSupported(__METHOD__);
        }

        return $this->_keywords = $keywords;
    }

    /**
     * Returns the class name of the reserved keywords list of this platform.
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException If not supported on this platform.
     */
    protected function getReservedKeywordsClass()
    {
        throw DBALException::notSupported(__METHOD__);
    }
}

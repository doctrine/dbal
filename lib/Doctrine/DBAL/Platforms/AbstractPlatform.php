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

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Connection,
    Doctrine\DBAL\Types,
    Doctrine\DBAL\Schema\Constraint,
    Doctrine\DBAL\Schema\Sequence,
    Doctrine\DBAL\Schema\Table,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\ForeignKeyConstraint,
    Doctrine\DBAL\Schema\TableDiff,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\ColumnDiff,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Events,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Event\SchemaCreateTableEventArgs,
    Doctrine\DBAL\Event\SchemaCreateTableColumnEventArgs,
    Doctrine\DBAL\Event\SchemaDropTableEventArgs,
    Doctrine\DBAL\Event\SchemaAlterTableEventArgs,
    Doctrine\DBAL\Event\SchemaAlterTableAddColumnEventArgs,
    Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs,
    Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs,
    Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs;

/**
 * Base class for all DatabasePlatforms. The DatabasePlatforms are the central
 * point of abstraction of platform-specific behaviors, features and SQL dialects.
 * They are a passive source of information.
 *
 *
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
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
     * @var array
     */
    protected $doctrineTypeMapping = null;

    /**
     * Contains a list of all columns that should generate parseable column comments for type-detection
     * in reverse engineering scenarios.
     *
     * @var array
     */
    protected $doctrineTypeComments = null;

    /**
     * @var \Doctrine\Common\EventManager
     */
    protected $_eventManager;

    /**
     * Holds the KeywordList instance for the current platform.
     *
     * @var \Doctrine\DBAL\Platforms\Keywords\KeywordList
     */
    protected $_keywords;

    /**
     * Constructor.
     */
    public function __construct() {}

    /**
     * Sets the EventManager used by the Platform.
     *
     * @param \Doctrine\Common\EventManager
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * Gets the EventManager used by the Platform.
     *
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Gets the SQL snippet that declares a boolean column.
     *
     * @param array $columnDef
     *
     * @return string
     */
    abstract public function getBooleanTypeDeclarationSQL(array $columnDef);

    /**
     * Gets the SQL snippet that declares a 4 byte integer column.
     *
     * @param array $columnDef
     *
     * @return string
     */
    abstract public function getIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Gets the SQL snippet that declares an 8 byte integer column.
     *
     * @param array $columnDef
     *
     * @return string
     */
    abstract public function getBigIntTypeDeclarationSQL(array $columnDef);

    /**
     * Gets the SQL snippet that declares a 2 byte integer column.
     *
     * @param array $columnDef
     *
     * @return string
     */
    abstract public function getSmallIntTypeDeclarationSQL(array $columnDef);

    /**
     * Gets the SQL snippet that declares common properties of an integer column.
     *
     * @param array $columnDef
     * @return string
     */
    abstract protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef);

    /**
     * Lazy load Doctrine Type Mappings
     *
     * @return void
     */
    abstract protected function initializeDoctrineTypeMappings();

    /**
     * Initialize Doctrine Type Mappings with the platform defaults
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
     * Gets the SQL snippet used to declare a VARCHAR column type.
     *
     * @param array $field
     *
     * @return string
     */
    public function getVarcharTypeDeclarationSQL(array $field)
    {
        if ( !isset($field['length'])) {
            $field['length'] = $this->getVarcharDefaultLength();
        }

        $fixed = (isset($field['fixed'])) ? $field['fixed'] : false;

        if ($field['length'] > $this->getVarcharMaxLength()) {
            return $this->getClobTypeDeclarationSQL($field);
        }

        return $this->getVarcharTypeDeclarationSQLSnippet($field['length'], $fixed);
    }

    /**
     * Get the SQL Snippet to create a GUID/UUID field.
     *
     * By default this maps directly to a VARCHAR and only maps to more
     * special datatypes when the underlying databases support this datatype.
     *
     * @param array $field
     *
     * @return string
     */
    public function getGuidTypeDeclarationSQL(array $field)
    {
        return $this->getVarcharTypeDeclarationSQL($field);
    }

    /**
     * @param integer $length
     * @param boolean $fixed
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        throw DBALException::notSupported('VARCHARs not supported by Platform.');
    }

    /**
     * Gets the SQL snippet used to declare a CLOB column type.
     *
     * @param array $field
     *
     * @return string
     */
    abstract public function getClobTypeDeclarationSQL(array $field);

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     *
     * @param array $field
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
     * Register a doctrine type to be used in conjunction with a column type of this platform.
     *
     * @param string $dbType
     * @param string $doctrineType
     *
     * @throws \Doctrine\DBAL\DBALException if the type is not found
     */
    public function registerDoctrineTypeMapping($dbType, $doctrineType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        if (!Types\Type::hasType($doctrineType)) {
            throw DBALException::typeNotFound($doctrineType);
        }

        $dbType = strtolower($dbType);
        $this->doctrineTypeMapping[$dbType] = $doctrineType;
    }

    /**
     * Get the Doctrine type that is mapped for the given database column type.
     *
     * @param  string $dbType
     *
     * @return string
     */
    public function getDoctrineTypeMapping($dbType)
    {
        if ($this->doctrineTypeMapping === null) {
            $this->initializeAllDoctrineTypeMappings();
        }

        $dbType = strtolower($dbType);

        if (!isset($this->doctrineTypeMapping[$dbType])) {
            throw new \Doctrine\DBAL\DBALException("Unknown database type ".$dbType." requested, " . get_class($this) . " may not support it.");
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Check if a database type is currently supported by this platform.
     *
     * @param string $dbType
     *
     * @return boolean
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
     * Initialize the Doctrine Type comments instance variable for in_array() checks.
     *
     * @return void
     */
    protected function initializeCommentedDoctrineTypes()
    {
        $this->doctrineTypeComments = array();

        foreach (Type::getTypesMap() as $typeName => $className) {
            $type = Type::getType($typeName);

            if ($type->requiresSQLCommentHint($this)) {
                $this->doctrineTypeComments[] = $typeName;
            }
        }
    }

    /**
     * Is it necessary for the platform to add a parsable type comment to allow reverse engineering the given type?
     *
     * @param Type $doctrineType
     *
     * @return boolean
     */
    public function isCommentedDoctrineType(Type $doctrineType)
    {
        if ($this->doctrineTypeComments === null) {
            $this->initializeCommentedDoctrineTypes();
        }

        return in_array($doctrineType->getName(), $this->doctrineTypeComments);
    }

    /**
     * Mark this type as to be commented in ALTER TABLE and CREATE TABLE statements.
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

        $this->doctrineTypeComments[] = $doctrineType instanceof Type ? $doctrineType->getName() : $doctrineType;
    }

    /**
     * Get the comment to append to a column comment that helps parsing this type in reverse engineering.
     *
     * @param Type $doctrineType
     * @return string
     */
    public function getDoctrineTypeComment(Type $doctrineType)
    {
        return '(DC2Type:' . $doctrineType->getName() . ')';
    }

    /**
     * Return the comment of a passed column modified by potential doctrine type comment hints.
     *
     * @param Column $column
     * @return string
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
        return "--";
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
     * Gets the maximum length of a varchar field.
     *
     * @return integer
     */
    public function getVarcharMaxLength()
    {
        return 4000;
    }

    /**
     * Gets the default length of a varchar field.
     *
     * @return integer
     */
    public function getVarcharDefaultLength()
    {
        return 255;
    }

    /**
     * Gets all SQL wildcard characters of the platform.
     *
     * @return array
     */
    public function getWildcards()
    {
        return array('%', '_');
    }

    /**
     * Returns the regular expression operator.
     *
     * @return string
     */
    public function getRegexpExpression()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns global unique identifier
     *
     * @return string to get global unique identifier
     */
    public function getGuidExpression()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the average value of a column
     *
     * @param string $column    the column to use
     *
     * @return string           generated sql including an AVG aggregate function
     */
    public function getAvgExpression($column)
    {
        return 'AVG(' .  $column . ')';
    }

    /**
     * Returns the number of rows (without a NULL value) of a column
     *
     * If a '*' is used instead of a column the number of selected rows
     * is returned.
     *
     * @param string|integer $column    the column to use
     *
     * @return string                   generated sql including a COUNT aggregate function
     */
    public function getCountExpression($column)
    {
        return 'COUNT(' . $column . ')';
    }

    /**
     * Returns the highest value of a column
     *
     * @param string $column    the column to use
     * @return string           generated sql including a MAX aggregate function
     */
    public function getMaxExpression($column)
    {
        return 'MAX(' . $column . ')';
    }

    /**
     * Returns the lowest value of a column
     *
     * @param string $column the column to use
     * @return string
     */
    public function getMinExpression($column)
    {
        return 'MIN(' . $column . ')';
    }

    /**
     * Returns the total sum of a column
     *
     * @param string $column the column to use
     * @return string
     */
    public function getSumExpression($column)
    {
        return 'SUM(' . $column . ')';
    }

    // scalar functions

    /**
     * Returns the md5 sum of a field.
     *
     * Note: Not SQL92, but common functionality
     *
     * @param string $column
     * @return string
     */
    public function getMd5Expression($column)
    {
        return 'MD5(' . $column . ')';
    }

    /**
     * Returns the length of a text field.
     *
     * @param string $column
     *
     * @return string
     */
    public function getLengthExpression($column)
    {
        return 'LENGTH(' . $column . ')';
    }

    /**
     * Returns the squared value of a column
     *
     * @param string $column    the column to use
     *
     * @return string           generated sql including an SQRT aggregate function
     */
    public function getSqrtExpression($column)
    {
        return 'SQRT(' . $column . ')';
    }

    /**
     * Rounds a numeric field to the number of decimals specified.
     *
     * @param string $column
     * @param integer $decimals
     *
     * @return string
     */
    public function getRoundExpression($column, $decimals = 0)
    {
        return 'ROUND(' . $column . ', ' . $decimals . ')';
    }

    /**
     * Returns the remainder of the division operation
     * $expression1 / $expression2.
     *
     * @param string $expression1
     * @param string $expression2
     *
     * @return string
     */
    public function getModExpression($expression1, $expression2)
    {
        return 'MOD(' . $expression1 . ', ' . $expression2 . ')';
    }

    /**
     * Trim a string, leading/trailing/both and with a given char which defaults to space.
     *
     * @param string $str
     * @param integer $pos
     * @param string $char has to be quoted already
     *
     * @return string
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        $posStr = '';
        $trimChar = ($char != false) ? $char . ' FROM ' : '';

        switch ($pos) {
            case self::TRIM_LEADING:
                $posStr = 'LEADING '.$trimChar;
                break;

            case self::TRIM_TRAILING:
                $posStr = 'TRAILING '.$trimChar;
                break;

            case self::TRIM_BOTH:
                $posStr = 'BOTH '.$trimChar;
                break;
        }

        return 'TRIM(' . $posStr . $str . ')';
    }

    /**
     * rtrim
     * returns the string $str with proceeding space characters removed
     *
     * @param string $str       literal string or column name
     *
     * @return string
     */
    public function getRtrimExpression($str)
    {
        return 'RTRIM(' . $str . ')';
    }

    /**
     * ltrim
     * returns the string $str with leading space characters removed
     *
     * @param string $str       literal string or column name
     *
     * @return string
     */
    public function getLtrimExpression($str)
    {
        return 'LTRIM(' . $str . ')';
    }

    /**
     * upper
     * Returns the string $str with all characters changed to
     * uppercase according to the current character set mapping.
     *
     * @param string $str       literal string or column name
     *
     * @return string
     */
    public function getUpperExpression($str)
    {
        return 'UPPER(' . $str . ')';
    }

    /**
     * lower
     * Returns the string $str with all characters changed to
     * lowercase according to the current character set mapping.
     *
     * @param string $str       literal string or column name
     *
     * @return string
     */
    public function getLowerExpression($str)
    {
        return 'LOWER(' . $str . ')';
    }

    /**
     * returns the position of the first occurrence of substring $substr in string $str
     *
     * @param string  $str       literal string
     * @param string  $substr    literal string to find
     * @param integer $startPos  position to start at, beginning of string by default
     *
     * @return string
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Returns the current system date.
     *
     * @return string
     */
    public function getNowExpression()
    {
        return 'NOW()';
    }

    /**
     * return string to call a function to get a substring inside an SQL statement
     *
     * Note: Not SQL92, but common functionality.
     *
     * SQLite only supports the 2 parameter variant of this function
     *
     * @param  string $value         an sql string literal or column name/alias
     * @param  integer $from         where to start the substring portion
     * @param  integer $length       the substring portion length
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
     * Returns a series of strings concatenated
     *
     * concat() accepts an arbitrary number of parameters. Each parameter
     * must contain an expression
     *
     * @param string $arg1, $arg2 ... $argN     strings that will be concatenated.
     *
     * @return string
     */
    public function getConcatExpression()
    {
        return join(' || ' , func_get_args());
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
     * @param string $expression
     *
     * @return string a logical expression
     */
    public function getNotExpression($expression)
    {
        return 'NOT(' . $expression . ')';
    }

    /**
     * Returns the SQL to check if a value is one in a set of
     * given values.
     *
     * in() accepts an arbitrary number of parameters. The first parameter
     * must always specify the value that should be matched against. Successive
     * must contain a logical expression or an array with logical expressions.
     * These expressions will be matched against the first parameter.
     *
     * @param string $column                the value that should be matched against
     * @param string|array<string> $values  values that will be matched against $column
     *
     * @return string logical expression
     */
    public function getInExpression($column, $values)
    {
        if ( ! is_array($values)) {
            $values = array($values);
        }

        // TODO: fix this code: the method does not exist
        $values = $this->getIdentifiers($values);

        if (count($values) == 0) {
            throw new \InvalidArgumentException('Values must not be empty.');
        }

        return $column . ' IN (' . implode(', ', $values) . ')';
    }

    /**
     * Returns SQL that checks if a expression is null.
     *
     * @param string $expression the expression that should be compared to null
     *
     * @return string logical expression
     */
    public function getIsNullExpression($expression)
    {
        return $expression . ' IS NULL';
    }

    /**
     * Returns SQL that checks if a expression is not null.
     *
     * @param string $expression the expression that should be compared to null
     *
     * @return string logical expression
     */
    public function getIsNotNullExpression($expression)
    {
        return $expression . ' IS NOT NULL';
    }

    /**
     * Returns SQL that checks if an expression evaluates to a value between
     * two values.
     *
     * The parameter $expression is checked if it is between $value1 and $value2.
     *
     * Note: There is a slight difference in the way BETWEEN works on some databases.
     * http://www.w3schools.com/sql/sql_between.asp. If you want complete database
     * independence you should avoid using between().
     *
     * @param string $expression the value to compare to
     * @param string $value1 the lower value to compare with
     * @param string $value2 the higher value to compare with
     *
     * @return string logical expression
     */
    public function getBetweenExpression($expression, $value1, $value2)
    {
        return $expression . ' BETWEEN ' .$value1 . ' AND ' . $value2;
    }

    public function getAcosExpression($value)
    {
        return 'ACOS(' . $value . ')';
    }

    public function getSinExpression($value)
    {
        return 'SIN(' . $value . ')';
    }

    public function getPiExpression()
    {
        return 'PI()';
    }

    public function getCosExpression($value)
    {
        return 'COS(' . $value . ')';
    }

    /**
     * Calculate the difference in days between the two passed dates.
     *
     * Computes diff = date1 - date2
     *
     * @param string $date1
     * @param string $date2
     *
     * @return string
     */
    public function getDateDiffExpression($date1, $date2)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Add the number of given days to a date.
     *
     * @param string $date
     * @param integer $days
     *
     * @return string
     */
    public function getDateAddDaysExpression($date, $days)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Substract the number of given days to a date.
     *
     * @param string $date
     * @param integer $days
     *
     * @return string
     */
    public function getDateSubDaysExpression($date, $days)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Add the number of given months to a date.
     *
     * @param string $date
     * @param integer $months
     *
     * @return string
     */
    public function getDateAddMonthExpression($date, $months)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Substract the number of given months to a date.
     *
     * @param string $date
     * @param integer $months
     *
     * @return string
     */
    public function getDateSubMonthExpression($date, $months)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Gets SQL bit AND comparison  expression
     *
     * @param   string $value1
     * @param   string $value2
     *
     * @return  string
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    /**
     * Gets SQL bit OR comparison expression
     *
     * @param   string $value1
     * @param   string $value2
     *
     * @return  string
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    public function getForUpdateSQL()
    {
        return 'FOR UPDATE';
    }

    /**
     * Honors that some SQL vendors such as MsSql use table hints for locking instead of the ANSI SQL FOR UPDATE specification.
     *
     * @param  string $fromClause
     * @param  integer $lockMode
     *
     * @return string
     */
    public function appendLockHint($fromClause, $lockMode)
    {
        return $fromClause;
    }

    /**
     * Get the sql snippet to append to any SELECT statement which locks rows in shared read lock.
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
     * Get the SQL snippet to append to any SELECT statement which obtains an exclusive lock on the rows.
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
     * Get the SQL snippet to drop an existing database
     *
     * @param string $database name of the database that should be dropped
     *
     * @return string
     */
    public function getDropDatabaseSQL($database)
    {
        return 'DROP DATABASE ' . $database;
    }

    /**
     * Drop a Table
     *
     * @throws \InvalidArgumentException
     *
     * @param  Table|string $table
     *
     * @return string
     */
    public function getDropTableSQL($table)
    {
        $tableArg = $table;

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        } else if(!is_string($table)) {
            throw new \InvalidArgumentException('getDropTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        if (null !== $this->_eventManager && $this->_eventManager->hasListeners(Events::onSchemaDropTable)) {
            $eventArgs = new SchemaDropTableEventArgs($tableArg, $this);
            $this->_eventManager->dispatchEvent(Events::onSchemaDropTable, $eventArgs);

            if ($eventArgs->isDefaultPrevented()) {
                return $eventArgs->getSql();
            }
        }

        return 'DROP TABLE ' . $table;
    }

    /**
     * Get SQL to safely drop a temporary table WITHOUT implicitly committing an open transaction.
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
     * Drop index from a table
     *
     * @param Index|string $name
     * @param string|Table $table
     *
     * @return string
     */
    public function getDropIndexSQL($index, $table = null)
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } else if(!is_string($index)) {
            throw new \InvalidArgumentException('AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        return 'DROP INDEX ' . $index;
    }

    /**
     * Get drop constraint sql
     *
     * @param  \Doctrine\DBAL\Schema\Constraint $constraint
     * @param  string|Table $table
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
     * @param  ForeignKeyConstraint|string $foreignKey
     * @param  Table|string $table
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
     * Gets the SQL statement(s) to create a table with the specified name, columns and constraints
     * on this platform.
     *
     * @param string $table The name of the table.
     * @param integer $createFlags
     *
     * @return array The sequence of SQL statements.
     */
    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        if ( ! is_int($createFlags)) {
            throw new \InvalidArgumentException("Second argument of AbstractPlatform::getCreateTableSQL() has to be integer.");
        }

        if (count($table->getColumns()) === 0) {
            throw DBALException::noColumnsSpecifiedForTable($table->getName());
        }

        $tableName = $table->getQuotedName($this);
        $options = $table->getOptions();
        $options['uniqueConstraints'] = array();
        $options['indexes'] = array();
        $options['primary'] = array();

        if (($createFlags&self::CREATE_INDEXES) > 0) {
            foreach ($table->getIndexes() as $index) {
                /* @var $index Index */
                if ($index->isPrimary()) {
                    $platform = $this;
                    $options['primary'] = array_map(function ($columnName) use ($table, $platform) {
                        return $table->getColumn($columnName)->getQuotedName($platform);
                    }, $index->getColumns());
                    $options['primary_index'] = $index;
                } else {
                    $options['indexes'][$index->getName()] = $index;
                }
            }
        }

        $columnSql = array();
        $columns = array();

        foreach ($table->getColumns() as $column) {
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
            $columnData['version'] = $column->hasPlatformOption("version") ? $column->getPlatformOption('version') : false;
            $columnData['comment'] = $this->getColumnComment($column);

            if (strtolower($columnData['type']) == "string" && $columnData['length'] === null) {
                $columnData['length'] = 255;
            }

            if (in_array($column->getName(), $options['primary'])) {
                $columnData['primary'] = true;
            }

            $columns[$columnData['name']] = $columnData;
        }

        if (($createFlags&self::CREATE_FOREIGNKEYS) > 0) {
            $options['foreignKeys'] = array();
            foreach ($table->getForeignKeys() as $fkConstraint) {
                $options['foreignKeys'][] = $fkConstraint;
            }
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
            foreach ($table->getColumns() as $column) {
                if ($this->getColumnComment($column)) {
                    $sql[] = $this->getCommentOnColumnSQL($tableName, $column->getName(), $this->getColumnComment($column));
                }
            }
        }

        return array_merge($sql, $columnSql);
    }

    public function getCommentOnColumnSQL($tableName, $columnName, $comment)
    {
        return "COMMENT ON COLUMN " . $tableName . "." . $columnName . " IS '" . $comment . "'";
    }

    /**
     * Gets the SQL used to create a table.
     *
     * @param string $tableName
     * @param array $columns
     * @param array $options
     *
     * @return array
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
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
            foreach($options['indexes'] as $index => $definition) {
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

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }

    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE TEMPORARY TABLE";
    }

    /**
     * Gets the SQL to create a sequence on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     *
     * @return string
     *
     * @throws DBALException
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Gets the SQL statement to change a sequence on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     *
     * @return string
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Gets the SQL to create a constraint on a table on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Constraint $constraint
     * @param string|Table $table
     *
     * @return string
     */
    public function getCreateConstraintSQL(Constraint $constraint, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $query = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $constraint->getQuotedName($this);

        $columns = array();
        foreach ($constraint->getColumns() as $column) {
            $columns[] = $column;
        }
        $columnList = '('. implode(', ', $columns) . ')';

        $referencesClause = '';
        if ($constraint instanceof Index) {
            if($constraint->isPrimary()) {
                $query .= ' PRIMARY KEY';
            } elseif ($constraint->isUnique()) {
                $query .= ' UNIQUE';
            } else {
                throw new \InvalidArgumentException(
                    'Can only create primary or unique constraints, no common indexes with getCreateConstraintSQL().'
                );
            }
        } else if ($constraint instanceof ForeignKeyConstraint) {
            $query .= ' FOREIGN KEY';

            $foreignColumns = array();
            foreach ($constraint->getForeignColumns() as $column) {
                $foreignColumns[] = $column;
            }

            $referencesClause = ' REFERENCES ' . $constraint->getQuotedForeignTableName($this) .
                ' (' . implode(', ', $foreignColumns) . ')';
        }
        $query .= ' '.$columnList.$referencesClause;

        return $query;
    }

    /**
     * Gets the SQL to create an index on a table on this platform.
     *
     * @param Index $index
     * @param string|Table $table name of the table on which the index is to be created
     *
     * @return string
     */
    public function getCreateIndexSQL(Index $index, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }
        $name = $index->getQuotedName($this);
        $columns = $index->getColumns();

        if (count($columns) == 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        $query = 'CREATE ' . $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ON ' . $table;
        $query .= ' (' . $this->getIndexFieldDeclarationListSQL($columns) . ')';

        return $query;
    }

    /**
     * Adds additional flags for index generation
     *
     * @param Index $index
     *
     * @return string
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        return $index->isUnique() ? 'UNIQUE ' : '';
    }

    /**
     * Get SQL to create an unnamed primary key constraint.
     *
     * @param Index $index
     * @param string|Table $table
     *
     * @return string
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY (' . $this->getIndexFieldDeclarationListSQL($index->getColumns()) . ')';
    }

    /**
     * Quotes a string so that it can be safely used as a table or column name,
     * even if it is a reserved word of the platform. This also detects identifier
     * chains separated by dot and quotes them independently.
     *
     * NOTE: Just because you CAN use quoted identifiers doesn't mean
     * you SHOULD use them.  In general, they end up causing way more
     * problems than they solve.
     *
     * @param string $str           identifier name to be quoted
     *
     * @return string               quoted identifier string
     */
    public function quoteIdentifier($str)
    {
        if (strpos($str, ".") !== false) {
            $parts = array_map(array($this, "quoteIdentifier"), explode(".", $str));

            return implode(".", $parts);
        }

        return $this->quoteSingleIdentifier($str);
    }

    /**
     * Quote a single identifier (no dot chain separation)
     *
     * @param string $str
     *
     * @return string
     */
    public function quoteSingleIdentifier($str)
    {
        $c = $this->getIdentifierQuoteCharacter();

        return $c . str_replace($c, $c.$c, $str) . $c;
    }

    /**
     * Create a new foreign key
     *
     * @param ForeignKeyConstraint  $foreignKey    ForeignKey instance
     * @param string|Table          $table         name of the table on which the foreign key is to be created
     *
     * @return string
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table)
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        $query = 'ALTER TABLE ' . $table . ' ADD ' . $this->getForeignKeyDeclarationSQL($foreignKey);

        return $query;
    }

    /**
     * Gets the sql statements for altering an existing table.
     *
     * The method returns an array of sql statements, since some platforms need several statements.
     *
     * @param TableDiff $diff
     *
     * @return array
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * @param Column $column
     * @param TableDiff $diff
     * @param array $columnSql
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
     * @param Column $column
     * @param TableDiff $diff
     * @param array $columnSql
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
     * @param ColumnDiff $columnDiff
     * @param TableDiff $diff
     * @param array $columnSql
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
     * @param string $oldColumnName
     * @param Column $column
     * @param TableDiff $diff
     * @param array $columnSql
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
     * @param TableDiff $diff
     * @param array $sql
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
     * Common code for alter table statement generation that updates the changed Index and Foreign Key definitions.
     *
     * @param TableDiff $diff
     *
     * @return array
     */
    protected function _getAlterTableIndexForeignKeySQL(TableDiff $diff)
    {
        return array_merge($this->getPreAlterTableIndexForeignKeySQL($diff), $this->getPostAlterTableIndexForeignKeySQL($diff));
    }

    /**
     * Get declaration of a number of fields in bulk
     *
     * @param array $fields  a multidimensional associative array.
     *      The first dimension determines the field name, while the second
     *      dimension is keyed with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
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
     * Obtain DBMS specific SQL code portion needed to declare a generic type
     * field to be used in statements like CREATE TABLE.
     *
     * @param string $name   name the field to be declared.
     * @param array  $field  associative array with the name of the properties
     *      of the field being declared as array indexes. Currently, the types
     *      of supported field properties are as follows:
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
     * @return string  DBMS specific SQL code portion that should be used to declare the column.
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($field);

            $charset = (isset($field['charset']) && $field['charset']) ?
                    ' ' . $this->getColumnCharsetDeclarationSQL($field['charset']) : '';

            $collation = (isset($field['collation']) && $field['collation']) ?
                    ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';

            $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : '';

            $unique = (isset($field['unique']) && $field['unique']) ?
                    ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = (isset($field['check']) && $field['check']) ?
                    ' ' . $field['check'] : '';

            $typeDecl = $field['type']->getSqlDeclaration($field, $this);
            $columnDef = $typeDecl . $charset . $default . $notnull . $unique . $check . $collation;
        }

        if ($this->supportsInlineColumnComments() && isset($field['comment']) && $field['comment']) {
            $columnDef .= " COMMENT '" . $field['comment'] . "'";
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * Gets the SQL snippet that declares a floating point column of arbitrary precision.
     *
     * @param array $columnDef
     *
     * @return string
     */
    public function getDecimalTypeDeclarationSQL(array $columnDef)
    {
        $columnDef['precision'] = ( ! isset($columnDef['precision']) || empty($columnDef['precision']))
            ? 10 : $columnDef['precision'];
        $columnDef['scale'] = ( ! isset($columnDef['scale']) || empty($columnDef['scale']))
            ? 0 : $columnDef['scale'];

        return 'NUMERIC(' . $columnDef['precision'] . ', ' . $columnDef['scale'] . ')';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $field      field definition array
     *
     * @return string           DBMS specific SQL code portion needed to set a default value
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        $default = empty($field['notnull']) ? ' DEFAULT NULL' : '';

        if (isset($field['default'])) {
            $default = " DEFAULT '".$field['default']."'";
            if (isset($field['type'])) {
                if (in_array((string)$field['type'], array("Integer", "BigInteger", "SmallInteger"))) {
                    $default = " DEFAULT ".$field['default'];
                } else if ((string)$field['type'] == 'DateTime' && $field['default'] == $this->getCurrentTimestampSQL()) {
                    $default = " DEFAULT ".$this->getCurrentTimestampSQL();
                } else if ((string)$field['type'] == 'Time' && $field['default'] == $this->getCurrentTimeSQL()) {
                    $default = " DEFAULT ".$this->getCurrentTimeSQL();
                } else if ((string)$field['type'] == 'Date' && $field['default'] == $this->getCurrentDateSQL()) {
                    $default = " DEFAULT ".$this->getCurrentDateSQL();
                } else if ((string) $field['type'] == 'Boolean') {
                    $default = " DEFAULT '" . $this->convertBooleans($field['default']) . "'";
                }
            }
        }
        return $default;
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set a CHECK constraint
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $definition     check definition
     *
     * @return string               DBMS specific SQL code portion needed to set a CHECK constraint
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
     * Obtain DBMS specific SQL code portion needed to set a unique
     * constraint declaration to be used in statements like CREATE TABLE.
     *
     * @param string $name          name of the unique constraint
     * @param Index $index          index definition
     *
     * @return string               DBMS specific SQL code portion needed
     *                              to set a constraint
     */
    public function getUniqueConstraintDeclarationSQL($name, Index $index)
    {
        if (count($index->getColumns()) === 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return 'CONSTRAINT ' . $name . ' UNIQUE ('
             . $this->getIndexFieldDeclarationListSQL($index->getColumns())
             . ')';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param string $name          name of the index
     * @param Index $index          index definition
     *
     * @return string               DBMS specific SQL code portion needed to set an index
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        if (count($index->getColumns()) === 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ('
             . $this->getIndexFieldDeclarationListSQL($index->getColumns())
             . ')';
    }

    /**
     * getCustomTypeDeclarationSql
     * Obtain SQL code portion needed to create a custom column,
     * e.g. when a field has the "columnDefinition" keyword.
     * Only "AUTOINCREMENT" and "PRIMARY KEY" are added if appropriate.
     *
     * @param array $columnDef
     *
     * @return string
     */
    public function getCustomTypeDeclarationSQL(array $columnDef)
    {
        return $columnDef['columnDefinition'];
    }

    /**
     * getIndexFieldDeclarationList
     * Obtain DBMS specific SQL code portion needed to set an index
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $fields
     *
     * @return string
     */
    public function getIndexFieldDeclarationListSQL(array $fields)
    {
        $ret = array();

        foreach ($fields as $field => $definition) {
            if (is_array($definition)) {
                $ret[] = $field;
            } else {
                $ret[] = $definition;
            }
        }

        return implode(', ', $ret);
    }

    /**
     * A method to return the required SQL string that fits between CREATE ... TABLE
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
     * @param  string $tableName
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
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey
     *
     * @return string  DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     *                 of a field declaration.
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        $sql  = $this->getForeignKeyBaseDeclarationSQL($foreignKey);
        $sql .= $this->getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $sql;
    }

    /**
     * Return the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param ForeignKeyConstraint $foreignKey     foreign key definition
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
     * returns given referential action in uppercase if valid, otherwise throws
     * an exception
     *
     * @throws \InvalidArgumentException if unknown referential action given
     *
     * @param string $action    foreign key referential action
     *
     * @return string
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
                throw new \InvalidArgumentException('Invalid foreign key action: ' . $upper);
        }
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the FOREIGN KEY constraint
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param ForeignKeyConstraint $foreignKey
     *
     * @return string
     */
    public function getForeignKeyBaseDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        $sql = '';
        if (strlen($foreignKey->getName())) {
            $sql .= 'CONSTRAINT ' . $foreignKey->getQuotedName($this) . ' ';
        }
        $sql .= 'FOREIGN KEY (';

        if (count($foreignKey->getLocalColumns()) === 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'local' required.");
        }
        if (count($foreignKey->getForeignColumns()) === 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'foreign' required.");
        }
        if (strlen($foreignKey->getForeignTableName()) === 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'foreignTable' required.");
        }

        $sql .= implode(', ', $foreignKey->getLocalColumns())
              . ') REFERENCES '
              . $foreignKey->getQuotedForeignTableName($this) . ' ('
              . implode(', ', $foreignKey->getForeignColumns()) . ')';

        return $sql;
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the UNIQUE constraint
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @return string  DBMS specific SQL code portion needed to set the UNIQUE constraint
     *                 of a field declaration.
     */
    public function getUniqueFieldDeclarationSQL()
    {
        return 'UNIQUE';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the CHARACTER SET
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $charset   name of the charset
     *
     * @return string  DBMS specific SQL code portion needed to set the CHARACTER SET
     *                 of a field declaration.
     */
    public function getColumnCharsetDeclarationSQL($charset)
    {
        return '';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the COLLATION
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $collation   name of the collation
     *
     * @return string  DBMS specific SQL code portion needed to set the COLLATION
     *                 of a field declaration.
     */
    public function getColumnCollationDeclarationSQL($collation)
    {
        return '';
    }

    /**
     * Whether the platform prefers sequences for ID generation.
     * Subclasses should override this method to return TRUE if they prefer sequences.
     *
     * @return boolean
     */
    public function prefersSequences()
    {
        return false;
    }

    /**
     * Whether the platform prefers identity columns (eg. autoincrement) for ID generation.
     * Subclasses should override this method to return TRUE if they prefer identity columns.
     *
     * @return boolean
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
     * @param mixed $item
     *
     * @return mixed
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $k => $value) {
                if (is_bool($value)) {
                    $item[$k] = (int) $value;
                }
            }
        } else if (is_bool($item)) {
            $item = (int) $item;
        }

        return $item;
    }

    /**
     * Gets the SQL specific for the platform to get the current date.
     *
     * @return string
     */
    public function getCurrentDateSQL()
    {
        return 'CURRENT_DATE';
    }

    /**
     * Gets the SQL specific for the platform to get the current time.
     *
     * @return string
     */
    public function getCurrentTimeSQL()
    {
        return 'CURRENT_TIME';
    }

    /**
     * Gets the SQL specific for the platform to get the current timestamp
     *
     * @return string
     */
    public function getCurrentTimestampSQL()
    {
        return 'CURRENT_TIMESTAMP';
    }

    /**
     * Get sql for transaction isolation level Connection constant
     *
     * @param integer $level
     *
     * @return string
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

    public function getListDatabasesSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListSequencesSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListTableConstraintsSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListTablesSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListUsersSQL()
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Get the SQL to list all views of a database or user.
     *
     * @param string $database
     *
     * @return string
     */
    public function getListViewsSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Get the list of indexes for the current database.
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
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getListTableForeignKeysSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getCreateViewSQL($name, $sql)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getDropViewSQL($name)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Get the SQL snippet to drop an existing sequence
     *
     * @param  \Doctrine\DBAL\Schema\Sequence $sequence
     *
     * @return string
     */
    public function getDropSequenceSQL($sequence)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getSequenceNextValSQL($sequenceName)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * create a new database
     *
     * @param string $database name of the database that should be created
     *
     * @return string
     */
    public function getCreateDatabaseSQL($database)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Get sql to set the transaction isolation level
     *
     * @param integer $level
     *
     * @return string
     */
    public function getSetTransactionIsolationSQL($level)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Obtain DBMS specific SQL to be used to create datetime fields in
     * statements like CREATE TABLE
     *
     * @param array $fieldDeclaration
     *
     * @return string
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Obtain DBMS specific SQL to be used to create datetime with timezone offset fields.
     *
     * @param array $fieldDeclaration
     *
     * @return string
     */
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        return $this->getDateTimeTypeDeclarationSQL($fieldDeclaration);
    }


    /**
     * Obtain DBMS specific SQL to be used to create date fields in statements
     * like CREATE TABLE.
     *
     * @param array $fieldDeclaration
     *
     * @return string
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * Obtain DBMS specific SQL to be used to create time fields in statements
     * like CREATE TABLE.
     *
     * @param array $fieldDeclaration
     *
     * @return string
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function getFloatDeclarationSQL(array $fieldDeclaration)
    {
        return 'DOUBLE PRECISION';
    }

    /**
     * Gets the default transaction isolation level of the platform.
     *
     * @return integer The default isolation level.
     *
     * @see Doctrine\DBAL\Connection\TRANSACTION_* constants.
     */
    public function getDefaultTransactionIsolationLevel()
    {
        return Connection::TRANSACTION_READ_COMMITTED;
    }

    /* supports*() methods */

    /**
     * Whether the platform supports sequences.
     *
     * @return boolean
     */
    public function supportsSequences()
    {
        return false;
    }

    /**
     * Whether the platform supports identity columns.
     * Identity columns are columns that receive an auto-generated value from the
     * database on insert of a row.
     *
     * @return boolean
     */
    public function supportsIdentityColumns()
    {
        return false;
    }

    /**
     * Whether the platform supports indexes.
     *
     * @return boolean
     */
    public function supportsIndexes()
    {
        return true;
    }

    /**
     * Whether the platform supports altering tables.
     *
     * @return boolean
     */
    public function supportsAlterTable()
    {
        return true;
    }

    /**
     * Whether the platform supports transactions.
     *
     * @return boolean
     */
    public function supportsTransactions()
    {
        return true;
    }

    /**
     * Whether the platform supports savepoints.
     *
     * @return boolean
     */
    public function supportsSavepoints()
    {
        return true;
    }

    /**
     * Whether the platform supports releasing savepoints.
     *
     * @return boolean
     */
    public function supportsReleaseSavepoints()
    {
        return $this->supportsSavepoints();
    }

    /**
     * Whether the platform supports primary key constraints.
     *
     * @return boolean
     */
    public function supportsPrimaryConstraints()
    {
        return true;
    }

    /**
     * Does the platform supports foreign key constraints?
     *
     * @return boolean
     */
    public function supportsForeignKeyConstraints()
    {
        return true;
    }

    /**
     * Does this platform supports onUpdate in foreign key constraints?
     *
     * @return boolean
     */
    public function supportsForeignKeyOnUpdate()
    {
        return ($this->supportsForeignKeyConstraints() && true);
    }

    /**
     * Whether the platform supports database schemas.
     *
     * @return boolean
     */
    public function supportsSchemas()
    {
        return false;
    }

    /**
     * Can this platform emulate schemas?
     *
     * Platforms that either support or emulate schemas don't automatically
     * filter a schema for the namespaced elements in {@link
     * AbstractManager#createSchema}.
     *
     * @return boolean
     */
    public function canEmulateSchemas()
    {
        return false;
    }

    /**
     * Some databases don't allow to create and drop databases at all or only with certain tools.
     *
     * @return boolean
     */
    public function supportsCreateDropDatabase()
    {
        return true;
    }

    /**
     * Whether the platform supports getting the affected rows of a recent
     * update/delete type query.
     *
     * @return boolean
     */
    public function supportsGettingAffectedRows()
    {
        return true;
    }

    /**
     * Does this platform support to add inline column comments as postfix.
     *
     * @return boolean
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * Does this platform support the proprietary syntax "COMMENT ON asset"
     *
     * @return boolean
     */
    public function supportsCommentOnStatement()
    {
        return false;
    }

    /**
     * Does this platform have native guid type.
     *
     * @return boolean
     */
    public function hasNativeGuidType()
    {
        return false;
    }

    public function getIdentityColumnNullInsertSQL()
    {
        return "";
    }

    /**
     * Does this platform views ?
     *
     * @return boolean
     */
    public function supportsViews()
    {
        return true;
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
     * Modify limit query
     *
     * @param string $query
     * @param integer $limit
     * @param integer $offset
     *
     * @return string
     */
    final public function modifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit !== null) {
            $limit = (int)$limit;
        }

        if ($offset !== null) {
            $offset = (int)$offset;

            if ($offset < 0) {
                throw new DBALException("LIMIT argument offset=$offset is not valid");
            }
            if ($offset > 0 && ! $this->supportsLimitOffset()) {
                throw new DBALException(sprintf("Platform %s does not support offset values in limit queries.", $this->getName()));
            }
        }

        return $this->doModifyLimitQuery($query, $limit, $offset);
    }

    /**
     * Adds an driver-specific LIMIT clause to the query
     *
     * @param string $query
     * @param integer $limit
     * @param integer $offset
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
     * Does the database platform support offsets in modify limit clauses?
     *
     * @return boolean
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
     * @return integer
     */
    public function getMaxIdentifierLength()
    {
        return 63;
    }

    /**
     * Get the insert sql for an empty insert statement
     *
     * @param string $tableName
     * @param string $identifierColumnName
     *
     * @return string $sql
     */
    public function getEmptyIdentityInsertSQL($tableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $tableName . ' (' . $identifierColumnName . ') VALUES (null)';
    }

    /**
     * Generate a Truncate Table SQL statement for a given table.
     *
     * Cascade is not supported on many platforms but would optionally cascade the truncate by
     * following the foreign keys.
     *
     * @param  string $tableName
     * @param  boolean $cascade
     *
     * @return string
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        return 'TRUNCATE '.$tableName;
    }

    /**
     * This is for test reasons, many vendors have special requirements for dummy statements.
     *
     * @return string
     */
    public function getDummySelectSQL()
    {
        return 'SELECT 1';
    }

    /**
     * Generate SQL to create a new savepoint
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
     * Generate SQL to release a savepoint
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
     * Generate SQL to rollback a savepoint
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
     * Return the keyword list instance of this platform.
     *
     * Throws exception if no keyword list is specified.
     *
     * @throws DBALException
     *
     * @return \Doctrine\DBAL\Platforms\Keywords\KeywordList
     */
    final public function getReservedKeywordsList()
    {
        // Check for an existing instantiation of the keywords class.
        if ($this->_keywords) {
            return $this->_keywords;
        }

        $class = $this->getReservedKeywordsClass();
        $keywords = new $class;
        if ( ! $keywords instanceof \Doctrine\DBAL\Platforms\Keywords\KeywordList) {
            throw DBALException::notSupported(__METHOD__);
        }

        // Store the instance so it doesn't need to be generated on every request.
        $this->_keywords = $keywords;

        return $keywords;
    }

    /**
     * The class name of the reserved keywords list.
     *
     * @return string
     */
    protected function getReservedKeywordsClass()
    {
        throw DBALException::notSupported(__METHOD__);
    }
}

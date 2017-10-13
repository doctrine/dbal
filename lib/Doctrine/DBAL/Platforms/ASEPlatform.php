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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ASE\ASEException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\Keywords\ASEKeywords;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Constraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\ASE\BigIntType;
use Doctrine\DBAL\Types\ASE\DateImmutableType;
use Doctrine\DBAL\Types\ASE\DateTimeImmutableType;
use Doctrine\DBAL\Types\ASE\DateTimeType;
use Doctrine\DBAL\Types\ASE\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\ASE\DateTimeTzType;
use Doctrine\DBAL\Types\ASE\DateType;
use Doctrine\DBAL\Types\ASE\DecimalType;
use Doctrine\DBAL\Types\ASE\PatchedType;
use Doctrine\DBAL\Types\ASE\TimeImmutableType;
use Doctrine\DBAL\Types\ASE\TimeType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * The ASEPlatform provides the behavior, features and SQL dialect of the
 * ASE database platform.
 *
 * @since 2.6
 * @author Maximilian Ruta <mr@xtain.net>
 */
class ASEPlatform extends AbstractPlatform
{
    /**
     * @const int
     */
    const LENGTH_LIMIT_VARCHAR = 16384;

    /**
     * @const int
     */
    const LENGTH_LIMIT_BINARY = 16384;

    /**
     * @const int
     */
    const LENGTH_LIMIT_VARBINARY = 16384;

    /**
     * @const int
     */
    const CS_DATES_SHORT_ALT = 0;

    /**
     * @const int
     */
    const CS_DATES_SHORT = 1;

    /**
     * @const int
     */
    const CS_DATES_LONG = 2;

    /**
     * @const int
     */
    const CS_DATES_LONG_ALT = 3;

    /**
     * @const int
     */
    const CS_DATES_MDYHMS = 4;

    /**
     * @const int
     */
    const CS_ANSI = -1;

    /**
     * @const string
     */
    const CS_DATES_SHORT_ALT_DATETIME = 'M j Y g:iA';

    /**
     * @const string
     */
    const CS_DATES_SHORT_ALT_DATE = 'M j Y \\1\\2\\:\\0\\0\\A\\M';

    /**
     * @const string
     */
    const CS_DATES_SHORT_ALT_TIME = '\\J\\a\\n\\ \\1\\ \\1\\9\\0\\0\\ g:iA';

    /**
     * @const string
     */
    const CS_DATES_SHORT_DATETIME = 'M j Y g:iA';

    /**
     * @const string
     */
    const CS_DATES_SHORT_DATE = 'M j Y';

    /**
     * @const string
     */
    const CS_DATES_SHORT_TIME = 'g:iA';

    /**
     * @const string
     */
    const CS_DATES_LONG_DATETIME = 'M j Y h:i:s:\\0\\0\\0A';

    /**
     * @const string
     */
    const CS_DATES_LONG_DATE = 'M j Y';

    /**
     * @const string
     */
    const CS_DATES_LONG_TIME = 'h:i:s:uA';

    /**
     * @const string
     */
    const CS_DATES_LONG_ALT_DATETIME = 'M j Y g:i:s:\\0\\0\\0A';

    /**
     * @const string
     */
    const CS_DATES_LONG_ALT_DATE = 'M j Y \\1\\2\\:\\0\\0\\:\\0\\0\\:\\0\\0\\0\\A\\M';

    /**
     * @const string
     */
    const CS_DATES_LONG_ALT_TIME = '\\J\\a\\n\\ \\1\\ \\1\\9\\0\\0\\ g:i:s:\\0\\0\\0A';

    /**
     * @const string
     */
    const CS_DATES_MDYHMS_DATETIME = 'M j Y H:i:s';

    /**
     * @const string
     */
    const CS_DATES_MDYHMS_DATE = 'M j Y';

    /**
     * @const string
     */
    const CS_DATES_MDYHMS_TIME = 'H:i:s';

    /**
     * @var array
     */
    protected $config;

    /**
     * ASEPlatform constructor.
     *
     * @param $config
     */
    public function __construct($config = array())
    {
        $this->config = $config;

        if (!isset($this->config['date_format'])) {
            $this->config['date_format'] = self::CS_ANSI;
        }

        self::monkeyPatchTypes();
    }

    protected static function monkeyPatchTypes()
    {
        static $patched;

        if (isset($patched) && $patched) {
            return;
        }

        $patched = true;

        $patchMap = array(
            Type::BIGINT => BigIntType::class,
            Type::DECIMAL => DecimalType::class,
            Type::DATETIME => DateTimeType::class,
            Type::DATETIMETZ => DateTimeTzType::class,
            Type::DATE => DateType::class,
            Type::TIME => TimeType::class,
        );

        if (defined(Type::class . '::DATETIME_IMMUTABLE')) {
            $patchMap[Type::DATETIME_IMMUTABLE] = DateTimeImmutableType::class;
            $patchMap[Type::DATETIMETZ_IMMUTABLE] = DateTimeTzImmutableType::class;
            $patchMap[Type::DATE_IMMUTABLE] = DateImmutableType::class;
            $patchMap[Type::TIME_IMMUTABLE] = TimeImmutableType::class;
        }

        foreach ($patchMap as $name => $override) {
            $instance = Type::getType($name);
            if (!($instance instanceof PatchedType) &&
                $override instanceof PatchedType) {
                Type::overrideType($name, $override);
                $newInstance = Type::getType($name);

                if (!($newInstance instanceof PatchedType)) {
                    throw new \LogicException('We should have overwritten the type');
                }

                $newInstance->setParent($instance);
            }
        }
    }

    /**
     * {@inheritDoc}
     * @license New BSD, code from Zend Framework
     */
    public static function quote($value, $type=null)
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if ($type === null) {
            if (is_float($value)) {
                return sprintf('%F', $value);
            } elseif (is_int($value)) {
                return $value;
            }

            return "'" . str_replace("'", "''", $value) . "'";
        } else {
            switch ($type) {
                case \PDO::PARAM_INT:
                case \PDO::PARAM_BOOL:
                    return $value;
                case \PDO::PARAM_STR:
                default:
                    return "'" . str_replace("'", "''", $value) . "'";
            }
        }
    }

    public function wrapByMasterContext($sql)
    {
        // In ASE some statements can only be executed in the context of the master database
        return 'DECLARE @olddb varchar(255) ' .
               'SELECT @olddb = DB_NAME() ' .
               'USE master ' .
               $sql . ' ' .
               'USE @olddb';
    }

    public function getCreateDatabaseDeviceSQL(array $device)
    {
        if (!isset($device['name'])) {
            throw new ASEException('Device without name given');
        }

        if (!ctype_alnum($device['name'])) {
            throw new ASEException('Devicename only allows alphanumeric characters');
        }

        $sql = $device['name'];
        if (isset($device['size'])) {
            $sql .= '=' . self::quote($device['size']);
        }

        return $sql . ', ';
    }

    public function getCreateDatabaseOnSQL()
    {
        $sql = '';

        $dataDevices = array();
        $logDevices = array();
        if (isset($this->config['devices']) && !empty($this->config['devices'])) {
            if (isset($this->config['devices']['data']) && !empty($this->config['devices']['data'])) {
                $dataDevices = $this->config['devices']['data'];
            }

            if (isset($this->config['devices']['log']) && !empty($this->config['devices']['log'])) {
                $logDevices = $this->config['devices']['log'];
            }
        }

        if (!empty($dataDevices)) {
            $sql .= ' ON ';
            foreach ($dataDevices as $dataDevice) {
                $sql .= $this->getCreateDatabaseDeviceSQL($dataDevice);
            }

            $sql = rtrim($sql, ', ');
        }

        if (!empty($logDevices)) {
            $sql .= ' LOG ON ';
            foreach ($logDevices as $logDevice) {
                $sql .= $this->getCreateDatabaseDeviceSQL($logDevice);
            }

            $sql = rtrim($sql, ', ');
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $field)
    {
        return 'TINYINT';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerUnsignedDeclarationSQL($field) . 'INT' .
            $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerUnsignedDeclarationSQL($field) . 'BIGINT' .
            $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $field)
    {
        return $this->_getCommonIntegerUnsignedDeclarationSQL($field) . 'SMALLINT' .
            $this->_getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        return (!empty($columnDef['autoincrement'])) ? ' IDENTITY' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerUnsignedDeclarationSQL(array $columnDef)
    {
        return !empty($columnDef['unsigned']) ? ' UNSIGNED ' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = array(
            'bigint' => 'bigint',
            'numeric' => 'decimal',
            'bit' => 'boolean',
            'smallint' => 'smallint',
            'decimal' => 'decimal',
            'smallmoney' => 'integer',
            'int' => 'integer',
            'tinyint' => 'smallint',
            'money' => 'integer',
            'float' => 'float',
            'real' => 'float',
            'double' => 'float',
            'double precision' => 'float',
            'smalldatetime' => 'datetime',
            'datetime' => 'datetime',
            'char' => 'string',
            'varchar' => 'string',
            'text' => 'text',
            'nchar' => 'string',
            'nvarchar' => 'string',
            'ntext' => 'text',
            'binary' => 'binary',
            'varbinary' => 'binary',
            'image' => 'blob',
            'uniqueidentifier' => 'guid',
            'date' => 'datetime',
            'daten' => 'date',
            'datetimn' => 'datetime',
            'decimaln' => 'decimal',
            // 'extended type' => 'extended type', # todo ???
            'floatn' => 'float',
            'intn' => 'int',
            'longsysname' => 'string',
            'moneyn' => 'integer',
            'numericn' => 'decimal',
            'sysname' => 'string',
            'time' => 'time',
            'timen' => 'time',
            'timestamp' => 'integer',
            'ubigint' => 'integer',
            'uint' => 'integer',
            'uintn' => 'integer',
            'unichar' => 'string',
            'unitext' => 'text',
            'univarchar' => 'string',
            'usmallint' => 'smallint'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGuidTypeDeclarationSQL(array $field)
    {
        return 'UNIQUEIDENTIFIER';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length ? 'NCHAR(' . $length . ')' : 'CHAR('.$this->getVarcharDefaultLength().')') : ($length ? 'NVARCHAR(' . $length . ')' : 'NVARCHAR('.$this->getVarcharDefaultLength().')');
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $this->getVarcharTypeDeclarationSQLSnippet($length, $fixed);
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'TEXT';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'NVARCHAR(' . $this->getBinaryMaxLength() . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ase';
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierQuoteCharacter()
    {
        return parent::getIdentifierQuoteCharacter();
    }

    /**
     * {@inheritdoc}
     */
    public function getVarcharMaxLength()
    {
        return self::LENGTH_LIMIT_VARCHAR;
    }

    /**
     * {@inheritdoc}
     */
    public function getVarcharDefaultLength()
    {
        return 255;
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryMaxLength()
    {
        return self::LENGTH_LIMIT_VARBINARY;
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryDefaultLength()
    {
        return 255;
    }

    /**
     * {@inheritdoc}
     */
    public function getGuidExpression()
    {
        return 'NEWID()';
    }

    /**
     * {@inheritdoc}
     */
    public function getMd5Expression($column)
    {
        return 'HASH(' . $column . ', \'md5\')';
    }

    /**
     * {@inheritdoc}
     */
    public function getLengthExpression($column)
    {
        return 'CHAR_LENGTH(' . $column . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getModExpression($expression1, $expression2)
    {
        return '(' . $expression1 . ' % ' . $expression2 . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getTrimExpression($str, $pos = self::TRIM_UNSPECIFIED, $char = false)
    {
        if ( ! $char) {
            switch ($pos) {
                case self::TRIM_LEADING:
                    $trimFn = 'LTRIM';
                    break;

                case self::TRIM_TRAILING:
                    $trimFn = 'RTRIM';
                    break;

                default:
                    return 'LTRIM(RTRIM(' . $str . '))';
            }

            return $trimFn . '(' . $str . ')';
        }

        /** Original query used to get those expressions
        declare @c varchar(100) = 'xxxBarxxx', @trim_char char(1) = 'x';
        declare @pat varchar(10) = '%[^' + @trim_char + ']%';
        select @c as string
        , @trim_char as trim_char
        , stuff(@c, 1, patindex(@pat, @c) - 1, null) as trim_leading
        , reverse(stuff(reverse(@c), 1, patindex(@pat, reverse(@c)) - 1, null)) as trim_trailing
        , reverse(stuff(reverse(stuff(@c, 1, patindex(@pat, @c) - 1, null)), 1, patindex(@pat, reverse(stuff(@c, 1, patindex(@pat, @c) - 1, null))) - 1, null)) as trim_both;
         */
        $pattern = "'%[^' + $char + ']%'";

        if ($pos == self::TRIM_LEADING) {
            return 'stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)';
        }

        if ($pos == self::TRIM_TRAILING) {
            return 'reverse(stuff(reverse(' . $str . '), 1, patindex(' . $pattern . ', reverse(' . $str . ')) - 1, null))';
        }

        return 'reverse(stuff(reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null)), 1, patindex(' . $pattern . ', reverse(stuff(' . $str . ', 1, patindex(' . $pattern . ', ' . $str . ') - 1, null))) - 1, null))';
    }
    /**
     * {@inheritdoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos == false) {
            return 'CHARINDEX(' . $substr . ', ' . $str . ')';
        }

        return 'CHARINDEX(' . $substr . ', ' . $str . ', ' . $startPos . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getNowExpression()
    {
        return 'getdate()';
    }

    /**
     * {@inheritdoc}
     */
    public function getSubstringExpression($value, $from, $length = null)
    {
        if ($length === null) {
            $length = $this->getLengthExpression($value) . ' - ' . $from . ' + 1';
        }

        return 'SUBSTRING(' . $value . ', ' . $from . ', ' . $length . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getConcatExpression()
    {
        $args = func_get_args();

        return '(' . implode(' + ', $args) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getNotExpression($expression)
    {
        return parent::getNotExpression($expression);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return 'DATEDIFF(day, ' . $date2 . ',' . $date1 . ')';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        $factorClause = '';

        if ('-' === $operator) {
            $factorClause = '-1 * ';
        }

        return 'DATEADD(' . $unit . ', ' . $factorClause . $interval . ', ' . $date . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getForUpdateSQL()
    {
        return '';
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
        if (!ctype_alnum($database)) {
            throw new \InvalidArgumentException('Database name only allows alphanumeric characters');
        }
        // In ASE you can only drop databases in the context of the master database
        return $this->wrapByMasterContext('DROP DATABASE ' . $database);
    }

    /**
     * {@inheritDoc}
     */
    public function getDropIndexSQL($index, $table = null)
    {
        if ($index instanceof Index) {
            $index = $index->getQuotedName($this);
        } elseif (!is_string($index)) {
            throw new \InvalidArgumentException('AbstractPlatform::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        if (!isset($table)) {
            return 'DROP INDEX ' . $index;
        }

        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return "IF EXISTS (SELECT * FROM sysobjects WHERE name = '$index')
                    ALTER TABLE " . $table . " DROP CONSTRAINT " . $index . "
                ELSE
                    DROP INDEX " . $table . "."  . $index;
    }

    /**
     * {@inheritDoc}
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
        $table = $table->getQuotedName($this);

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommentOnColumnSQL($tableName, $columnName, $comment)
    {
        return "";
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = array())
    {
        $defaultConstraintsSql = array();
        $commentsSql           = array();

        // @todo does other code breaks because of this?
        // force primary keys to be not null
        foreach ($columns as &$column) {
            if (isset($column['primary']) && $column['primary']) {
                $column['notnull'] = true;
            }

            // Build default constraints SQL statements.
            if (isset($column['default'])) {
                $defaultConstraintsSql[] = 'ALTER TABLE ' . $tableName .
                    ' ADD' . $this->getDefaultConstraintDeclarationSQL($tableName, $column);
            }
        }

        $columnListSql = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && !empty($options['primary'])) {
            $flags = '';
            if (isset($options['primary_index'])) {
                $flags = $this->getCreatePrimaryKeySQLFlags($options['primary_index']);
            }
            $columnListSql .= ', PRIMARY KEY' . $flags . ' (' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        $lock = 'datarows';
        if (isset($options['lock'])) {
            $lock = strtolower(trim($options['lock']));
        }

        $query .= ' LOCK ' . $lock;

        $sql[] = $query;

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $index) {
                $sql[] = $this->getCreateIndexSQL($index, $tableName);
            }
        }

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return array_merge($sql, $commentsSql, $defaultConstraintsSql);
    }

    /**
     * Returns the SQL snippet for declaring a default constraint.
     *
     * @param string $table  Name of the table to return the default constraint declaration for.
     * @param array  $column Column definition.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDefaultConstraintDeclarationSQL($table, array $column)
    {
        if ( ! isset($column['default'])) {
            throw new \InvalidArgumentException("Incomplete column definition. 'default' required.");
        }

        $columnName = new Identifier($column['name']);

        return
            ' CONSTRAINT ' .
            $this->generateDefaultConstraintName($table, $column['name']) .
            $this->getDefaultValueDeclarationSQL($column) .
            ' FOR ' . $columnName->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return "CREATE TABLE";
    }

    /**
     * Returns the SQL to create an index on a table on this platform.
     *
     * @param \Doctrine\DBAL\Schema\Index        $index
     * @param \Doctrine\DBAL\Schema\Table|string $table The name of the table on which the index is to be created.
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

        if (count($columns) == 0) {
            throw new \InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        if ($index->isPrimary()) {
            return $this->getCreatePrimaryKeySQL($index, $table);
        }

        $query = 'CREATE ' . $this->getCreateIndexSQLFlags($index) . 'INDEX ' . $name . ' ON ' . $table;
        $query .= $this->getCreateIndexSQLFlagsWith($index);
        $query .= ' (' . $this->getIndexFieldDeclarationListSQL($columns) . ')' . $this->getPartialIndexSQL($index);

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    protected function getCreateIndexSQLFlags(Index $index)
    {
        $type = '';
        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        }

        if ($index->hasFlag('clustered')) {
            $type .= 'CLUSTERED ';
        } elseif ($index->hasFlag('nonclustered')) {
            $type .= 'NONCLUSTERED ';
        }

        return $type;
    }

    /**
     * Returns the SQL for primary key index flags
     *
     * @param \Doctrine\DBAL\Schema\Index        $index
     *
     * @return string
     */
    protected function getCreateIndexSQLFlagsWith(Index $index)
    {
        $flags = '';

        if ($index->hasOption('fillfactor') ||
            $index->hasOption('max_rows_per_page') ||
            $index->hasOption('reservepagegap') ||
            $index->hasOption('dml_logging')) {

            $flags .= ' WITH';

            if ($index->hasOption('fillfactor')) {
                $flags .= "fillfactor = " . $index->getOption('fillfactor') . ", ";
            }
            if ($index->hasOption('max_rows_per_page')) {
                $flags .= "max_rows_per_page = " . $index->getOption('max_rows_per_page') . ", ";
            }
            if ($index->hasOption('reservepagegap')) {
                $flags .= "reservepagegap = " . $index->getOption('reservepagegap') . ", ";
            }

            $flags = rtrim($flags, ", ");

        }

        return $flags;
    }

    /**
     * Returns the SQL for primary key index flags
     *
     * @param \Doctrine\DBAL\Schema\Index        $index
     *
     * @return string
     */
    protected function getCreatePrimaryKeySQLFlags(Index $index)
    {
        $flags = '';

        if ($index->hasFlag('clustered')) {
            $flags .= 'CLUSTERED ';
        } elseif ($index->hasFlag('nonclustered')) {
            $flags .= 'NONCLUSTERED ';
        }

        if ($index->hasFlag('asc') || $index->hasFlag('desc')) {
            if ($index->hasFlag('asc')) {
                $flags .= ' ASC';
            } else {
                $flags .= ' DESC';
            }
        }

        $flags .= $this->getCreateIndexSQLFlagsWith($index);

        return $flags;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table)
    {
        return 'ALTER TABLE ' . $table . ' ADD PRIMARY KEY' .
            $this->getCreatePrimaryKeySQLFlags($index) .
            ' (' . $this->getIndexFieldDeclarationListSQL($index->getQuotedColumns($this)) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        return array(
            sprintf(
                "EXEC sp_rename N'%s.%s', N'%s', N'index'",
                $tableName,
                $oldIndexName,
                $index->getQuotedName($this)
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * Modifies column declaration order as it differs in Microsoft SQL Server.
     */
    public function getColumnDeclarationSQL($name, array $field)
    {
        if (isset($field['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($field);
        } else {
            $collation = (isset($field['collation']) && $field['collation']) ?
                ' ' . $this->getColumnCollationDeclarationSQL($field['collation']) : '';

            $notnull = (isset($field['notnull']) && $field['notnull']) ? ' NOT NULL' : ' NULL ';

            $check = (isset($field['check']) && $field['check']) ?
                ' ' . $field['check'] : '';

            $typeDecl = $field['type']->getSqlDeclaration($field, $this);
            $columnDef = $typeDecl . $collation . $notnull . $check;
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * Obtains DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $field The field definition array.
     *
     * @return string DBMS specific SQL code portion needed to set a default value.
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        if (isset($field['default'])) {
            if (isset($field['type'])) {
                if ((string) $field['type'] == 'Boolean') {
                    return " DEFAULT " . $this->convertBooleans($field['default']);
                }
            }
        }

        return parent::getDefaultValueDeclarationSQL($field);
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexDeclarationSQL($name, Index $index)
    {
        // Index declaration in statements like CREATE TABLE is not supported.
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getTemporaryTableSQL()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getTemporaryTableName($tableName)
    {
        return '#' . $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        return parent::getForeignKeyDeclarationSQL($foreignKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getUniqueFieldDeclarationSQL()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function prefersIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function convertBooleans($item)
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_bool($value) || is_numeric($item)) {
                    $item[$key] = ($value) ? 1 : 0;
                }
            }
        } elseif (is_bool($item) || is_numeric($item)) {
            $item = ($item) ? 1 : 0;
        }

        return $item;
    }

    /**
     * @param string $value
     * @return string
     */
    public function fixDateTimeToDatabaseValue($value)
    {
        switch ($this->config['date_format']) {
            case self::CS_DATES_SHORT_ALT:
                $value = preg_replace("/^(.{3})([\\s][0-9][\\s])/", "$1 $2", $value);
                $value = preg_replace("/(.*?[^\\s])([\\s][0-9]\\:[0-9]+[A-Z]{2})$/", "$1 $2", $value);
                break;
            case self::CS_DATES_LONG_ALT:
                $value = preg_replace("/^(.{3})([\\s][0-9][\\s])/", "$1 $2", $value);
                $value = preg_replace("/(\\:)([0-9]{3})([0-9]*)(AM|PM)$/", "$1$2$4", $value);
                $value = preg_replace("/(.*?[^\\s])([\\s][0-9]\\:[0-9]+\\:[0-9]+\\:[0-9]+[A-Z]{2})$/", "$1 $2", $value);
                break;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function fixDateTimeFromDatabaseValue($value)
    {
        if (is_string($value)) {
            switch ($this->config['date_format']) {
                case self::CS_DATES_SHORT_ALT:
                    $value = preg_replace("/[\\s]([\\s][0-9]\\:[0-9]+[A-Z]{2})$/", "$1$2", $value);
                    $value = preg_replace("/^(.{3}[\\s])[\\s]([0-9])/", "$1$2", $value);
                    break;
                case self::CS_DATES_LONG_ALT:
                    $value = preg_replace("/[\\s]([\\s][0-9]\\:[0-9]+[A-Z]{2})$/", "$1$2", $value);
                    $value = preg_replace("/(\\:)([0-9]{3})[0-9]*(AM|PM)$/", "$1\${2}000$3", $value);
                    $value = preg_replace("/^(.{3}[\\s])[\\s]([0-9])/", "$1$2", $value);
                    break;
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertDateTimeToDatabaseValue(\DateTime $value = null)
    {
        $value = parent::convertDateTimeToDatabaseValue($value);

        $value = $this->fixDateTimeToDatabaseValue($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertFromDateTime($value)
    {
        $value = $this->fixDateTimeFromDatabaseValue($value);

        return parent::convertFromDateTime($value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertDateTimeTzToDatabaseValue(\DateTime $value = null)
    {
        $value = parent::convertDateTimeToDatabaseValue($value);

        $value = $this->fixDateTimeToDatabaseValue($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertFromDateTimeTz($value)
    {
        $value = $this->fixDateTimeFromDatabaseValue($value);

        return parent::convertFromDateTime($value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertDateToDatabaseValue(\DateTime $value = null)
    {
        $value = parent::convertDateToDatabaseValue($value);

        $value = $this->fixDateTimeToDatabaseValue($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertFromDate($value)
    {
        $value = $this->fixDateTimeFromDatabaseValue($value);

        return parent::convertFromDate($value);
    }

    /**
     * {@inheritdoc}
     */
    public function convertTimeToDatabaseValue(\DateTime $value = null)
    {
        $value = parent::convertTimeToDatabaseValue($value);

        $value = $this->fixDateTimeToDatabaseValue($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertFromTime($value)
    {
        $value = $this->fixDateTimeFromDatabaseValue($value);

        return parent::convertFromTime($value);
    }

    /**
     * Some platforms need to convert aliases
     *
     * @param string $column
     * @param string $alias
     * @param array  $mapping
     *
     * @return string
     */
    public function selectAliasColumn($column, $alias, array $mapping = array())
    {
        if (isset($mapping['id']) && $mapping['id']) {
            $mapping['id'] = false;

            $dbType = Type::getType($mapping['type'])->getSQLDeclaration($mapping, $this);

            if ($dbType !== null) {
                return 'CONVERT(' . $dbType . ', ' . $column . ') AS ' . $alias;
            }
        }

        return $column . ' AS ' . $alias;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentDateSQL()
    {
        return 'convert(date, getdate())';
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentTimeSQL()
    {
        return 'convert(time, getdate())';
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentTimestampSQL()
    {
        return 'GETDATE()';
    }

    /**
     * {@inheritdoc}
     */
    protected function _getTransactionIsolationLevelSQL($level)
    {
        switch ($level) {
            case Connection::TRANSACTION_READ_UNCOMMITTED:
                return '0';
            case Connection::TRANSACTION_READ_COMMITTED:
                return '1';
            case Connection::TRANSACTION_REPEATABLE_READ:
                return '2';
            case Connection::TRANSACTION_SERIALIZABLE:
                return '3';
            default:
                throw new \InvalidArgumentException('Invalid isolation level:' . $level);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getListDatabasesSQL()
    {
        return "SELECT name FROM master.dbo.sysdatabases";
    }

    /**
     * {@inheritdoc}
     */
    public function getListNamespacesSQL()
    {
        return "SELECT name FROM master.dbo.sysusers WHERE name NOT IN('guest')";
    }

    /**
     * {@inheritdoc}
     *
     * @todo Where is this used? Which information should be retrieved?
     */
    public function getListTableConstraintsSQL($table)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        $prefix = "";
        if ($database != null) {
            $prefix = $database . ".dbo.";
        }

        return 'SELECT  col.name,
                        t.name as \'type\',
                        col.length,
                        (
                            CASE WHEN
                              status & 8 = 8
                            THEN
                              0
                            ELSE
                              1
                            END
                        ) AS notnull,
                        \'\' AS [default],
                        col.scale,
                        col.prec as precision,
                        (
                            CASE WHEN
                              status & 128 = 128
                            THEN
                              1
                            ELSE
                              0
                            END
                        ) AS autoincrement,
                        \'NULL\' AS [collation],
                        \'\' AS [comment]
                FROM ' . $prefix . 'syscolumns AS col
                JOIN ' . $prefix . 'systypes AS t ON t.usertype = col.usertype
                WHERE col.id = object_id(\'' . $prefix . $table .'\')
        ';
    }

    /**
     * {@inheritdoc}
     */
    public function getListTablesSQL()
    {
        return "SELECT name FROM sysobjects WHERE type = 'U' ORDER BY name";
    }

    /**
     * {@inheritdoc}
     */
    public function getListViewsSQL($database)
    {
        if ($database != null) {
            $prefix = $database . ".dbo.";
        }

        return 'SELECT name FROM '.$prefix.'sysobjects WHERE type = \'V\' AND name != \'sysquerymetrics\' ORDER BY name';
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        #todo read flags

        $prefix = "";
        if ($currentDatabase != null) {
            $prefix = $currentDatabase . ".dbo.";
        }

        $getForIndex = function($i) use($prefix, $table, $currentDatabase) {
            return '
                SELECT
                    idx.name AS key_name,
                    index_col(\'' . $prefix . $table .'\', idx.indid, '.$i.') AS column_name,
                    (
                      CASE WHEN
                        idx.status & 2 = 2
                      THEN
                        0
                      ELSE
                        1
                      END
                    ) AS non_unique,
                    (
                      CASE WHEN
                        idx.status & 2048 = 2048
                      THEN
                        1
                      ELSE
                        0
                      END
                    ) AS [primary],
                    \'\' as flags,
                    idx.indid AS idxpos,
                    '.$i.' AS colpos
                FROM ' . $prefix . 'sysobjects AS tbl
                JOIN ' . $prefix . 'sysindexes AS idx on tbl.id = idx.id
                WHERE
                    idx.keycnt > '.($i - 1).' AND
                    tbl.id = object_id(\'' . $prefix . $table .'\')
            ';
        };

        $sql = "";

        for ($i = 1; $i <= $this->getMaxIndexFields(); $i++) {
            $sql .= $getForIndex($i) . "\nUNION\n";
        }
        $sql = preg_replace('/UNION$/', '', rtrim($sql));

        $sql = '
            SELECT
                key_name,
                column_name,
                non_unique,
                [primary],
                [flags]
            FROM ('.$sql.') AS sub
            WHERE
                sub.column_name != NULL
            ORDER BY
                sub.idxpos,
                sub.colpos
        ';

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function getListTableForeignKeysSQL($table)
    {
        $prefix = "";

        $getForIndex = function($i) use($prefix, $table) {
            return '
                SELECT
                  co.name AS foreign_key,
                  fu.name AS schema_name,
                  fo.name AS table_name,
                  (SELECT name FROM ' . $prefix . 'syscolumns WHERE id=c.tableid AND colid=c.fokey'.($i).') AS column_name,
                  ru.name AS reference_schema_name,
                  ro.name AS reference_table_name,
                  (SELECT name FROM ' . $prefix . 'syscolumns WHERE id=c.tableid AND colid=c.refkey'.($i).') AS reference_column_name,
                  c.indexid AS idxpos,
                  '.$i.' AS colpos
                FROM
                  ' . $prefix . 'sysreferences AS c
                JOIN ' . $prefix . 'sysobjects AS co ON c.constrid = co.id
                JOIN ' . $prefix . 'sysobjects AS fo ON c.tableid = fo.id
                JOIN ' . $prefix . 'sysobjects AS ro ON c.reftabid = ro.id
                JOIN ' . $prefix . 'sysusers AS fu ON fu.uid = fo.uid
                JOIN ' . $prefix . 'sysusers AS ru ON ru.uid = ro.uid
                WHERE
                  c.keycnt > '.($i - 1).' AND
                  fo.name =  \'' . $table .'\'
            ';
        };


        $sql = "";

        for ($i = 1; $i <= $this->getMaxIndexFields(); $i++) {
            $sql .= $getForIndex($i) . "\nUNION\n";
        }
        $sql = preg_replace('/UNION$/', '', rtrim($sql));

        $sql = '
            SELECT
                foreign_key,
                schema_name,
                table_name,
                column_name,
                reference_schema_name,
                reference_table_name,
                reference_column_name
            FROM ('.$sql.') AS sub
            ORDER BY
                sub.idxpos,
                sub.colpos
        ';

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateViewSQL($name, $sql)
    {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropViewSQL($name)
    {
        return 'DROP VIEW ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($name)
    {
        if (!ctype_alnum($name)) {
            throw new \InvalidArgumentException('Database name only allows alphanumeric characters');
        }

        // In ASE you can only create databases in the context of the master database
        return $this->wrapByMasterContext(rtrim('CREATE DATABASE ' . $name . ' ' . $this->getCreateDatabaseOnSQL()));
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATETIME';
    }

    /**
     * {@inheritdoc}
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIME';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsReleaseSavepoints()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsForeignKeyOnUpdate()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSchemas()
    {
        // ASE supports schemas, but not in the way doctrine works with them
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultSchemaName()
    {
        return 'dbo';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsInlineColumnComments()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNativeGuidType()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNativeJsonType()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormatString()
    {
        switch ($this->config['date_format']) {
            case self::CS_DATES_SHORT_ALT:
                return self::CS_DATES_SHORT_ALT_DATETIME;
            case self::CS_DATES_SHORT:
                return self::CS_DATES_SHORT_DATETIME;
            case self::CS_DATES_LONG:
                return self::CS_DATES_LONG_DATETIME;
            case self::CS_DATES_LONG_ALT:
                return self::CS_DATES_LONG_ALT_DATETIME;
            case self::CS_DATES_MDYHMS:
                return self::CS_DATES_MDYHMS_DATETIME;
        }

        return parent::getDateTimeFormatString();
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString()
    {
        switch ($this->config['date_format']) {
            case self::CS_DATES_SHORT_ALT:
                return self::CS_DATES_SHORT_ALT_DATE;
            case self::CS_DATES_SHORT:
                return self::CS_DATES_SHORT_DATE;
            case self::CS_DATES_LONG:
                return self::CS_DATES_LONG_DATE;
            case self::CS_DATES_LONG_ALT:
                return self::CS_DATES_LONG_ALT_DATE;
            case self::CS_DATES_MDYHMS:
                return self::CS_DATES_MDYHMS_DATE;
        }

        return parent::getDateFormatString();
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        switch ($this->config['date_format']) {
            case self::CS_DATES_SHORT_ALT:
                return self::CS_DATES_SHORT_ALT_TIME;
            case self::CS_DATES_SHORT:
                return self::CS_DATES_SHORT_TIME;
            case self::CS_DATES_LONG:
                return self::CS_DATES_LONG_TIME;
            case self::CS_DATES_LONG_ALT:
                return self::CS_DATES_LONG_ALT_TIME;
            case self::CS_DATES_MDYHMS:
                return self::CS_DATES_MDYHMS_TIME;
        }

        return parent::getTimeFormatString();
    }

    /**
     * {@inheritdoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset)
    {
        if ($limit === null) {
            return $query;
        }

        $limit = (int) $limit;

        if ($offset === 0 || $offset === null) {
            $selectPattern = '/^(\s*SELECT\s+(?:DISTINCT\s+)?)(.*)$/i';
            $replacePattern = sprintf('$1%s $2', "TOP $limit");
            $query = preg_replace($selectPattern, $replacePattern, $query);
            return $query;
        }

        $offset = (int) $offset;

        $start   = $offset + 1;
        $end     = $offset + $limit;

        // We'll find a SELECT or SELECT distinct and prepend TOP n to it
        $selectPattern = '/^(\s*SELECT\s+(?:DISTINCT\s+)?)(.*?)(.*)(\s+FROM\s+.*)$/i';
        $parts = array();
        $matches = array();
        if (preg_match($selectPattern, $query, $matches)) {
            $intoPart = " , doctrine_rownum=identity(10) INTO #dctrn_cte ";

            $parts['select'] = $matches[1] . "TOP $end " . $matches[2];
            $parts['from'] = $matches[3] . $intoPart;

            $matchesFrom = array();
            if (!preg_match('/SELECT.*\s+FROM.*$/i', $matches[3]) && preg_match('/^(.*)\s+FROM(.*)$/i', $matches[3], $matchesFrom)) {
                $parts['from'] = $matchesFrom[1] . $intoPart . " FROM " . $matchesFrom[2];
            }

            $query = $parts['select'] . $parts['from'] . $matches[4];
        }

        // Build a new limited query around the original, using a CTE
        return sprintf(
            "%s SELECT * FROM #dctrn_cte WHERE doctrine_rownum BETWEEN %d AND %d DROP TABLE #dctrn_cte",
            $query,
            $start,
            $end
        );
    }

    /**
     * {@inheritdoc}
     *
     * ASE supports a maximum length of 128 bytes for identifiers.
     */
    public function fixSchemaElementName($schemaElementName)
    {
        $maxIdentifierLength = $this->getMaxIdentifierLength();

        if (strlen($schemaElementName) > $maxIdentifierLength) {
            return substr($schemaElementName, 0, $maxIdentifierLength);
        }

        return $schemaElementName;
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIndexFields()
    {
        return 16;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxIdentifierLength()
    {
        return 128;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmptyIdentityInsertSQL($quotedTableName, $identifierColumnName)
    {
        return 'INSERT INTO ' . $quotedTableName . ' VALUES ()';
    }

    /**
     * {@inheritDoc}
     */
    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE TABLE ' . $tableIdentifier->getQuotedName($this);
    }


    /**
     * {@inheritDoc}
     */
    public function createSavePoint($savepoint)
    {
        return 'SAVE TRANSACTION ' . $savepoint;
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavePoint($savepoint)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackSavePoint($savepoint)
    {
        return 'ROLLBACK TRANSACTION ' . $savepoint;
    }

    /**
     * {@inheritdoc}
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\ASEKeywords';
    }

    /**
     * Returns a unique default constraint name for a table and column.
     *
     * @param string $table  Name of the table to generate the unique default constraint name for.
     * @param string $column Name of the column in the table to generate the unique default constraint name for.
     *
     * @return string
     */
    private function generateDefaultConstraintName($table, $column)
    {
        return 'DF_' . $this->generateIdentifierName($table) . '_' . $this->generateIdentifierName($column);
    }

    /**
     * Returns a hash value for a given identifier.
     *
     * @param string $identifier Identifier to generate a hash value for.
     *
     * @return string
     */
    private function generateIdentifierName($identifier)
    {
        // Always generate name for unquoted identifiers to ensure consistency.
        $identifier = new Identifier($identifier);

        return strtoupper(dechex(crc32($identifier->getName())));
    }
}

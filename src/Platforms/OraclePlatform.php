<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;

use function array_merge;
use function count;
use function explode;
use function func_get_arg;
use function func_num_args;
use function implode;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtoupper;
use function substr;

/**
 * OraclePlatform.
 */
class OraclePlatform extends AbstractPlatform implements DatabaseIntrospectionSQLBuilder
{
    /**
     * Assertion for Oracle identifiers.
     *
     * @deprecated
     *
     * @link http://docs.oracle.com/cd/B19306_01/server.102/b14200/sql_elements008.htm
     *
     * @param string $identifier
     *
     * @return void
     *
     * @throws Exception
     */
    public static function assertValidIdentifier($identifier)
    {
        if (preg_match('(^(([a-zA-Z]{1}[a-zA-Z0-9_$#]{0,})|("[^"]+"))$)', $identifier) === 0) {
            throw new Exception('Invalid Oracle identifier');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSubstringExpression($string, $start, $length = null)
    {
        if ($length !== null) {
            return sprintf('SUBSTR(%s, %d, %d)', $string, $start, $length);
        }

        return sprintf('SUBSTR(%s, %d)', $string, $start);
    }

    /**
     * @deprecated Generate dates within the application.
     *
     * @param string $type
     *
     * @return string
     */
    public function getNowExpression($type = 'timestamp')
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4753',
            'OraclePlatform::getNowExpression() is deprecated. Generate dates within the application.'
        );

        switch ($type) {
            case 'date':
            case 'time':
            case 'timestamp':
            default:
                return 'TO_CHAR(CURRENT_TIMESTAMP, \'YYYY-MM-DD HH24:MI:SS\')';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getLocateExpression($str, $substr, $startPos = false)
    {
        if ($startPos === false) {
            return 'INSTR(' . $str . ', ' . $substr . ')';
        }

        return 'INSTR(' . $str . ', ' . $substr . ', ' . $startPos . ')';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit)
    {
        switch ($unit) {
            case DateIntervalUnit::MONTH:
            case DateIntervalUnit::QUARTER:
            case DateIntervalUnit::YEAR:
                switch ($unit) {
                    case DateIntervalUnit::QUARTER:
                        $interval *= 3;
                        break;

                    case DateIntervalUnit::YEAR:
                        $interval *= 12;
                        break;
                }

                return 'ADD_MONTHS(' . $date . ', ' . $operator . $interval . ')';

            default:
                $calculationClause = '';

                switch ($unit) {
                    case DateIntervalUnit::SECOND:
                        $calculationClause = '/24/60/60';
                        break;

                    case DateIntervalUnit::MINUTE:
                        $calculationClause = '/24/60';
                        break;

                    case DateIntervalUnit::HOUR:
                        $calculationClause = '/24';
                        break;

                    case DateIntervalUnit::WEEK:
                        $calculationClause = '*7';
                        break;
                }

                return '(' . $date . $operator . $interval . $calculationClause . ')';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDateDiffExpression($date1, $date2)
    {
        return sprintf('TRUNC(%s) - TRUNC(%s)', $date1, $date2);
    }

    /**
     * {@inheritDoc}
     */
    public function getBitAndComparisonExpression($value1, $value2)
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return "SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA')";
    }

    /**
     * {@inheritDoc}
     */
    public function getBitOrComparisonExpression($value1, $value2)
    {
        return '(' . $value1 . '-' .
                $this->getBitAndComparisonExpression($value1, $value2)
                . '+' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatePrimaryKeySQL(Index $index, $table): string
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        return 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $index->getQuotedName($this)
            . ' PRIMARY KEY (' . $this->getIndexFieldDeclarationListSQL($index) . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Need to specifiy minvalue, since start with is hidden in the system and MINVALUE <= START WITH.
     * Therefore we can use MINVALUE to be able to get a hint what START WITH was for later introspection
     * in {@see listSequences()}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               $this->getSequenceCacheSQL($sequence);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize()
               . $this->getSequenceCacheSQL($sequence);
    }

    /**
     * Cache definition for sequences
     *
     * @return string
     */
    private function getSequenceCacheSQL(Sequence $sequence)
    {
        if ($sequence->getCache() === 0) {
            return ' NOCACHE';
        }

        if ($sequence->getCache() === 1) {
            return ' NOCACHE';
        }

        if ($sequence->getCache() > 1) {
            return ' CACHE ' . $sequence->getCache();
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getSequenceNextValSQL($sequence)
    {
        return 'SELECT ' . $sequence . '.nextval FROM DUAL';
    }

    /**
     * {@inheritDoc}
     */
    public function getSetTransactionIsolationSQL($level)
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getTransactionIsolationLevelSQL($level)
    {
        switch ($level) {
            case TransactionIsolationLevel::READ_UNCOMMITTED:
                return 'READ UNCOMMITTED';

            case TransactionIsolationLevel::READ_COMMITTED:
                return 'READ COMMITTED';

            case TransactionIsolationLevel::REPEATABLE_READ:
            case TransactionIsolationLevel::SERIALIZABLE:
                return 'SERIALIZABLE';

            default:
                return parent::_getTransactionIsolationLevelSQL($level);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column)
    {
        return 'NUMBER(1)';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column)
    {
        return 'NUMBER(10)';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column)
    {
        return 'NUMBER(20)';
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column)
    {
        return 'NUMBER(5)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column)
    {
        return 'TIMESTAMP(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column)
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $column)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column)
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? ($length > 0 ? 'CHAR(' . $length . ')' : 'CHAR(2000)')
                : ($length > 0 ? 'VARCHAR2(' . $length . ')' : 'VARCHAR2(4000)');
    }

    /**
     * {@inheritdoc}
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        return 'RAW(' . ($length > 0 ? $length : $this->getBinaryMaxLength()) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getBinaryMaxLength()
    {
        return 2000;
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $column)
    {
        return 'CLOB';
    }

    /**
     * {@inheritDoc}
     */
    public function getListDatabasesSQL()
    {
        return 'SELECT username FROM all_users';
    }

    /**
     * {@inheritDoc}
     */
    public function getListSequencesSQL($database)
    {
        $database = $this->normalizeIdentifier($database);
        $database = $this->quoteStringLiteral($database->getName());

        return 'SELECT sequence_name, min_value, increment_by FROM sys.all_sequences ' .
               'WHERE SEQUENCE_OWNER = ' . $database;
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($name, array $columns, array $options = [])
    {
        $indexes            = $options['indexes'] ?? [];
        $options['indexes'] = [];
        $sql                = parent::_getCreateTableSQL($name, $columns, $options);

        foreach ($columns as $columnName => $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence']);
            }

            if (
                ! isset($column['autoincrement']) || ! $column['autoincrement'] &&
                (! isset($column['autoinc']) || ! $column['autoinc'])
            ) {
                continue;
            }

            $sql = array_merge($sql, $this->getCreateAutoincrementSql($columnName, $name));
        }

        foreach ($indexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $name);
        }

        return $sql;
    }

    public function getListDatabaseIndexesSQL(string $database): string
    {
        return $this->getListIndexesSQL($database);
    }

    /**
     * {@inheritDoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaOracleReader.html
     */
    public function getListTableIndexesSQL($table, $database = null)
    {
        return $this->getListIndexesSQL($database ?? '', $table);
    }

    private function getListIndexesSQL(string $database, ?string $table = null): string
    {
        $databaseIdentifier       = $this->normalizeIdentifier($database);
        $quotedDatabaseIdentifier = $this->quoteStringLiteral($databaseIdentifier->getName());

        $tableCondition = '';
        if ($table !== null) {
            $tableIdentifier       = $this->normalizeIdentifier($table);
            $quotedTableIdentifier = $this->quoteStringLiteral($tableIdentifier->getName());
            $tableCondition        = 'AND ind_col.table_name = ' . $quotedTableIdentifier;
        }

        return <<<SQL
              SELECT ind_col.table_name as table_name,
                     ind_col.index_name AS name,
                     ind.index_type AS type,
                     decode(ind.uniqueness, 'NONUNIQUE', 0, 'UNIQUE', 1) AS is_unique,
                     ind_col.column_name AS column_name,
                     ind_col.column_position AS column_pos,
                     con.constraint_type AS is_primary
                FROM all_ind_columns ind_col
           LEFT JOIN all_indexes ind ON ind.owner = ind_col.index_owner AND ind.index_name = ind_col.index_name
           LEFT JOIN all_constraints con ON  con.owner = ind_col.index_owner AND con.index_name = ind_col.index_name
               WHERE ind_col.index_owner = $quotedDatabaseIdentifier $tableCondition
            ORDER BY ind_col.table_name, ind_col.index_name, ind_col.column_position
SQL;
    }

    /**
     * {@inheritDoc}
     */
    public function getListTablesSQL()
    {
        return 'SELECT * FROM sys.user_tables';
    }

    /**
     * {@inheritDoc}
     */
    public function getListViewsSQL($database)
    {
        return 'SELECT view_name, text FROM sys.user_views';
    }

    /**
     * @internal The method should be only used from within the OraclePlatform class hierarchy.
     *
     * @param string $name
     * @param string $table
     * @param int    $start
     *
     * @return string[]
     */
    public function getCreateAutoincrementSql($name, $table, $start = 1)
    {
        $tableIdentifier   = $this->normalizeIdentifier($table);
        $quotedTableName   = $tableIdentifier->getQuotedName($this);
        $unquotedTableName = $tableIdentifier->getName();

        $nameIdentifier = $this->normalizeIdentifier($name);
        $quotedName     = $nameIdentifier->getQuotedName($this);
        $unquotedName   = $nameIdentifier->getName();

        $sql = [];

        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($tableIdentifier);

        $idx = new Index($autoincrementIdentifierName, [$quotedName], true, true);

        $sql[] = "DECLARE
  constraints_Count NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO constraints_Count
    FROM USER_CONSTRAINTS
   WHERE TABLE_NAME = '" . $unquotedTableName . "'
     AND CONSTRAINT_TYPE = 'P';
  IF constraints_Count = 0 OR constraints_Count = '' THEN
    EXECUTE IMMEDIATE '" . $this->getCreateConstraintSQL($idx, $quotedTableName) . "';
  END IF;
END;";

        $sequenceName = $this->getIdentitySequenceName(
            $tableIdentifier->isQuoted() ? $quotedTableName : $unquotedTableName,
            $nameIdentifier->isQuoted() ? $quotedName : $unquotedName
        );
        $sequence     = new Sequence($sequenceName, $start);
        $sql[]        = $this->getCreateSequenceSQL($sequence);

        $sql[] = 'CREATE TRIGGER ' . $autoincrementIdentifierName . '
   BEFORE INSERT
   ON ' . $quotedTableName . '
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   IF (:NEW.' . $quotedName . ' IS NULL OR :NEW.' . $quotedName . ' = 0) THEN
      SELECT ' . $sequenceName . '.NEXTVAL INTO :NEW.' . $quotedName . ' FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM User_Sequences
       WHERE Sequence_Name = \'' . $sequence->getName() . '\';
      SELECT :NEW.' . $quotedName . ' INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT ' . $sequenceName . '.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
      SELECT ' . $sequenceName . '.NEXTVAL INTO last_Sequence FROM DUAL;
   END IF;
END;';

        return $sql;
    }

    /**
     * @internal The method should be only used from within the OracleSchemaManager class hierarchy.
     *
     * Returns the SQL statements to drop the autoincrement for the given table name.
     *
     * @param string $table The table name to drop the autoincrement for.
     *
     * @return string[]
     */
    public function getDropAutoincrementSql($table)
    {
        $table                       = $this->normalizeIdentifier($table);
        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($table);
        $identitySequenceName        = $this->getIdentitySequenceName(
            $table->isQuoted() ? $table->getQuotedName($this) : $table->getName(),
            ''
        );

        return [
            'DROP TRIGGER ' . $autoincrementIdentifierName,
            $this->getDropSequenceSQL($identitySequenceName),
            $this->getDropConstraintSQL($autoincrementIdentifierName, $table->getQuotedName($this)),
        ];
    }

    /**
     * Normalizes the given identifier.
     *
     * Uppercases the given identifier if it is not quoted by intention
     * to reflect Oracle's internal auto uppercasing strategy of unquoted identifiers.
     *
     * @param string $name The identifier to normalize.
     *
     * @return Identifier The normalized identifier.
     */
    private function normalizeIdentifier($name)
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier : new Identifier(strtoupper($name));
    }

    /**
     * Adds suffix to identifier,
     *
     * if the new string exceeds max identifier length,
     * keeps $suffix, cuts from $identifier as much as the part exceeding.
     */
    private function addSuffix(string $identifier, string $suffix): string
    {
        $maxPossibleLengthWithoutSuffix = $this->getMaxIdentifierLength() - strlen($suffix);
        if (strlen($identifier) > $maxPossibleLengthWithoutSuffix) {
            $identifier = substr($identifier, 0, $maxPossibleLengthWithoutSuffix);
        }

        return $identifier . $suffix;
    }

    /**
     * Returns the autoincrement primary key identifier name for the given table identifier.
     *
     * Quotes the autoincrement primary key identifier name
     * if the given table name is quoted by intention.
     *
     * @param Identifier $table The table identifier to return the autoincrement primary key identifier name for.
     *
     * @return string
     */
    private function getAutoincrementIdentifierName(Identifier $table)
    {
        $identifierName = $this->addSuffix($table->getName(), '_AI_PK');

        return $table->isQuoted()
            ? $this->quoteSingleIdentifier($identifierName)
            : $identifierName;
    }

    public function getListDatabaseForeignKeysSQL(string $database): string
    {
        return $this->getListForeignKeysSQL($database);
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableForeignKeysSQL($table)
    {
        return $this->getListForeignKeysSQL(null, $table);
    }

    private function getListForeignKeysSQL(?string $database, ?string $table = null): string
    {
        $databaseCondition      = '';
        $constraintColumnsTable = 'all_cons_columns';
        $constraintTable        = 'all_constraints';
        if ($database !== null) {
            $databaseIdentifier = $this->normalizeIdentifier($database);
            $databaseCondition  = 'cols.owner = ' . $this->quoteStringLiteral($databaseIdentifier->getName());
        }

        $tableCondition = '';
        if ($table !== null) {
            $tableIdentifier = $this->normalizeIdentifier($table);
            $tableCondition  = ($database === null ? '' : 'AND ') .
                'cols.table_name = ' . $this->quoteStringLiteral($tableIdentifier->getName());
            if ($database === null) {
                $constraintColumnsTable = 'user_cons_columns';
                $constraintTable        = 'user_constraints';
            }
        }

        return <<<SQL
              SELECT cols.table_name,
                     alc.constraint_name,
                     alc.DELETE_RULE,
                     cols.column_name "local_column",
                     cols.position,
                     r_cols.table_name "references_table",
                     r_cols.column_name "foreign_column"
                FROM $constraintColumnsTable cols
           LEFT JOIN $constraintTable alc ON alc.owner = cols.owner AND alc.constraint_name = cols.constraint_name
           LEFT JOIN $constraintColumnsTable r_cols ON r_cols.owner = alc.r_owner AND
                     r_cols.constraint_name = alc.r_constraint_name AND
                     r_cols.position = cols.position
               WHERE $databaseCondition $tableCondition AND alc.constraint_type = 'R'
            ORDER BY cols.table_name, cols.constraint_name, cols.position
SQL;
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableConstraintsSQL($table)
    {
        $table = $this->normalizeIdentifier($table);
        $table = $this->quoteStringLiteral($table->getName());

        return 'SELECT * FROM user_constraints WHERE table_name = ' . $table;
    }

    public function getListDatabaseColumnsSQL(string $database): string
    {
        return $this->getListColumnsSQL($database);
    }

    /**
     * {@inheritDoc}
     */
    public function getListTableColumnsSQL($table, $database = null)
    {
        return $this->getListColumnsSQL($database, $table);
    }

    private function getListColumnsSQL(?string $database, ?string $table = null): string
    {
        $databaseCondition   = '';
        $tableColumnsTable   = 'all_tab_columns';
        $columnCommentsTable = 'all_col_comments';
        if ($database !== null) {
            $databaseIdentifier = $this->normalizeIdentifier($database);
            $databaseCondition  = 'c.owner = ' . $this->quoteStringLiteral($databaseIdentifier->getName());
        }

        $tableCondition = '';
        if ($table !== null) {
            $tableIdentifier = $this->normalizeIdentifier($table);
            $tableCondition  = ($database === null ? '' : 'AND ') .
                'c.table_name = ' . $this->quoteStringLiteral($tableIdentifier->getName());
            if ($database === null) {
                $constraintColumnsTable = 'user_tab_columns';
                $constraintTable        = 'user_col_comments';
            }
        }

        return <<<SQL
              SELECT c.*,
                     d.comments AS comments
                FROM $tableColumnsTable c
           LEFT JOIN $columnCommentsTable d ON d.OWNER = c.OWNER AND d.TABLE_NAME = c.TABLE_NAME AND
                     d.COLUMN_NAME = c.COLUMN_NAME
               WHERE $databaseCondition $tableCondition
            ORDER BY c.table_name, c.column_id
SQL;
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
        $table      = $table->getQuotedName($this);

        return 'ALTER TABLE ' . $table . ' DROP CONSTRAINT ' . $foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey)
    {
        $referentialAction = '';

        if ($foreignKey->hasOption('onDelete')) {
            $referentialAction = $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onDelete'));
        }

        if ($referentialAction !== '') {
            return ' ON DELETE ' . $referentialAction;
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyReferentialActionSQL($action)
    {
        $action = strtoupper($action);

        switch ($action) {
            case 'RESTRICT': // RESTRICT is not supported, therefore falling back to NO ACTION.
            case 'NO ACTION':
                // NO ACTION cannot be declared explicitly,
                // therefore returning empty string to indicate to OMIT the referential clause.
                return '';

            case 'CASCADE':
            case 'SET NULL':
                return $action;

            default:
                // SET DEFAULT is not supported, throw exception instead.
                throw new InvalidArgumentException('Invalid foreign key action: ' . $action);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateDatabaseSQL($name)
    {
        return 'CREATE USER ' . $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getDropDatabaseSQL($name)
    {
        return 'DROP USER ' . $name . ' CASCADE';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff)
    {
        $sql         = [];
        $commentsSQL = [];
        $columnSql   = [];

        $fields = [];

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $fields[] = $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());
            $comment  = $this->getColumnComment($column);

            if ($comment === null || $comment === '') {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $diff->getName($this)->getQuotedName($this),
                $column->getQuotedName($this),
                $comment
            );
        }

        if (count($fields) > 0) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this)
                . ' ADD (' . implode(', ', $fields) . ')';
        }

        $fields = [];
        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            $column = $columnDiff->column;

            // Do not generate column alteration clause if type is binary and only fixed property has changed.
            // Oracle only supports binary type columns with variable length.
            // Avoids unnecessary table alteration statements.
            if (
                $column->getType() instanceof BinaryType &&
                $columnDiff->hasChanged('fixed') &&
                count($columnDiff->changedProperties) === 1
            ) {
                continue;
            }

            $columnHasChangedComment = $columnDiff->hasChanged('comment');

            /**
             * Do not add query part if only comment has changed
             */
            if (! ($columnHasChangedComment && count($columnDiff->changedProperties) === 1)) {
                $columnInfo = $column->toArray();

                if (! $columnDiff->hasChanged('notnull')) {
                    unset($columnInfo['notnull']);
                }

                $fields[] = $column->getQuotedName($this) . $this->getColumnDeclarationSQL('', $columnInfo);
            }

            if (! $columnHasChangedComment) {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $diff->getName($this)->getQuotedName($this),
                $column->getQuotedName($this),
                $this->getColumnComment($column)
            );
        }

        if (count($fields) > 0) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this)
                . ' MODIFY (' . implode(', ', $fields) . ')';
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new Identifier($oldColumnName);

            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) .
                ' RENAME COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        $fields = [];
        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $fields[] = $column->getQuotedName($this);
        }

        if (count($fields) > 0) {
            $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this)
                . ' DROP (' . implode(', ', $fields) . ')';
        }

        $tableSql = [];

        if (! $this->onSchemaAlterTable($diff, $tableSql)) {
            $sql = array_merge($sql, $commentsSQL);

            $newName = $diff->getNewName();

            if ($newName !== false) {
                $sql[] = sprintf(
                    'ALTER TABLE %s RENAME TO %s',
                    $diff->getName($this)->getQuotedName($this),
                    $newName->getQuotedName($this)
                );
            }

            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnDeclarationSQL($name, array $column)
    {
        if (isset($column['columnDefinition'])) {
            $columnDef = $this->getCustomTypeDeclarationSQL($column);
        } else {
            $default = $this->getDefaultValueDeclarationSQL($column);

            $notnull = '';

            if (isset($column['notnull'])) {
                $notnull = $column['notnull'] ? ' NOT NULL' : ' NULL';
            }

            $unique = ! empty($column['unique']) ?
                ' ' . $this->getUniqueFieldDeclarationSQL() : '';

            $check = ! empty($column['check']) ?
                ' ' . $column['check'] : '';

            $typeDecl  = $column['type']->getSQLDeclaration($column, $this);
            $columnDef = $typeDecl . $default . $notnull . $unique . $check;
        }

        return $name . ' ' . $columnDef;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRenameIndexSQL($oldIndexName, Index $index, $tableName)
    {
        if (strpos($tableName, '.') !== false) {
            [$schema]     = explode('.', $tableName);
            $oldIndexName = $schema . '.' . $oldIndexName;
        }

        return ['ALTER INDEX ' . $oldIndexName . ' RENAME TO ' . $index->getQuotedName($this)];
    }

    /**
     * {@inheritdoc}
     */
    public function usesSequenceEmulatedIdentityColumns()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentitySequenceName($tableName, $columnName)
    {
        $table = new Identifier($tableName);

        // No usage of column name to preserve BC compatibility with <2.5
        $identitySequenceName = $this->addSuffix($table->getName(), '_SEQ');

        if ($table->isQuoted()) {
            $identitySequenceName = '"' . $identitySequenceName . '"';
        }

        $identitySequenceIdentifier = $this->normalizeIdentifier($identitySequenceName);

        return $identitySequenceIdentifier->getQuotedName($this);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCommentOnStatement()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4749',
            'OraclePlatform::getName() is deprecated. Identify platforms by their class.'
        );

        return 'oracle';
    }

    /**
     * {@inheritDoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset)
    {
        if ($limit === null && $offset <= 0) {
            return $query;
        }

        if (preg_match('/^\s*SELECT/i', $query) === 1) {
            if (preg_match('/\sFROM\s/i', $query) === 0) {
                $query .= ' FROM dual';
            }

            $columns = ['a.*'];

            if ($offset > 0) {
                $columns[] = 'ROWNUM AS doctrine_rownum';
            }

            $query = sprintf('SELECT %s FROM (%s) a', implode(', ', $columns), $query);

            if ($limit !== null) {
                $query .= sprintf(' WHERE ROWNUM <= %d', $offset + $limit);
            }

            if ($offset > 0) {
                $query = sprintf('SELECT * FROM (%s) WHERE doctrine_rownum >= %d', $query, $offset + 1);
            }
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreateTemporaryTableSnippetSQL()
    {
        return 'CREATE GLOBAL TEMPORARY TABLE';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d H:i:sP';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateFormatString()
    {
        return 'Y-m-d 00:00:00';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeFormatString()
    {
        return '1900-01-01 H:i:s';
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxIdentifierLength()
    {
        return 30;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsReleaseSavepoints()
    {
        return false;
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
    public function getDummySelectSQL()
    {
        $expression = func_num_args() > 0 ? func_get_arg(0) : '1';

        return sprintf('SELECT %s FROM DUAL', $expression);
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = [
            'binary_double'  => 'float',
            'binary_float'   => 'float',
            'binary_integer' => 'boolean',
            'blob'           => 'blob',
            'char'           => 'string',
            'clob'           => 'text',
            'date'           => 'date',
            'float'          => 'float',
            'integer'        => 'integer',
            'long'           => 'string',
            'long raw'       => 'blob',
            'nchar'          => 'string',
            'nclob'          => 'text',
            'number'         => 'integer',
            'nvarchar2'      => 'string',
            'pls_integer'    => 'boolean',
            'raw'            => 'binary',
            'rowid'          => 'string',
            'timestamp'      => 'datetime',
            'timestamptz'    => 'datetimetz',
            'urowid'         => 'string',
            'varchar'        => 'string',
            'varchar2'       => 'string',
        ];
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
     *
     * @deprecated Implement {@link createReservedKeywordsList()} instead.
     */
    protected function getReservedKeywordsClass()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4510',
            'OraclePlatform::getReservedKeywordsClass() is deprecated,'
            . ' use OraclePlatform::createReservedKeywordsList() instead.'
        );

        return Keywords\OracleKeywords::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column)
    {
        return 'BLOB';
    }

    public function getListTableCommentsSQL(string $table, ?string $database = null): string
    {
        $tableCommentsName = 'user_tab_comments';
        $ownerCondition    = '';

        if ($database !== null && $database !== '/') {
            $tableCommentsName = 'all_tab_comments';
            $ownerCondition    = ' AND owner = ' . $this->quoteStringLiteral(
                $this->normalizeIdentifier($database)->getName()
            );
        }

        return sprintf(
            <<<'SQL'
SELECT comments FROM %s WHERE table_name = %s%s
SQL
            ,
            $tableCommentsName,
            $this->quoteStringLiteral($this->normalizeIdentifier($table)->getName()),
            $ownerCondition
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\OracleSchemaManager;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;

use function array_merge;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_contains;
use function strlen;
use function strtoupper;
use function substr;

/**
 * OraclePlatform.
 */
class OraclePlatform extends AbstractPlatform
{
    public function __construct()
    {
        parent::__construct(UnquotedIdentifierFolding::UPPER);
    }

    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTR(%s, %s)', $string, $start);
        }

        return sprintf('SUBSTR(%s, %s, %s)', $string, $start, $length);
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('INSTR(%s, %s)', $string, $substring);
        }

        return sprintf('INSTR(%s, %s, %s)', $string, $substring, $start);
    }

    protected function getDateArithmeticIntervalExpression(
        string $date,
        string $operator,
        string $interval,
        DateIntervalUnit $unit,
    ): string {
        switch ($unit) {
            case DateIntervalUnit::MONTH:
            case DateIntervalUnit::QUARTER:
            case DateIntervalUnit::YEAR:
                switch ($unit) {
                    case DateIntervalUnit::QUARTER:
                        $interval = $this->multiplyInterval($interval, 3);
                        break;

                    case DateIntervalUnit::YEAR:
                        $interval = $this->multiplyInterval($interval, 12);
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

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return sprintf('TRUNC(%s) - TRUNC(%s)', $date1, $date2);
    }

    public function getBitAndComparisonExpression(string $value1, string $value2): string
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    public function getCurrentDatabaseExpression(): string
    {
        return "SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA')";
    }

    public function getBitOrComparisonExpression(string $value1, string $value2): string
    {
        return '(' . $value1 . '-' .
                $this->getBitAndComparisonExpression($value1, $value2)
                . '+' . $value2 . ')';
    }

    /**
     * {@inheritDoc}
     *
     * Need to specifiy minvalue, since start with is hidden in the system and MINVALUE <= START WITH.
     * Therefore we can use MINVALUE to be able to get a hint what START WITH was for later introspection
     * in {@see listSequences()}
     */
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               $this->getSequenceCacheSQL($sequence);
    }

    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize()
               . $this->getSequenceCacheSQL($sequence);
    }

    /**
     * Cache definition for sequences
     */
    private function getSequenceCacheSQL(Sequence $sequence): string
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

    public function getSequenceNextValSQL(string $sequence): string
    {
        return 'SELECT ' . $sequence . '.nextval FROM DUAL';
    }

    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    protected function _getTransactionIsolationLevelSQL(TransactionIsolationLevel $level): string
    {
        return match ($level) {
            TransactionIsolationLevel::READ_UNCOMMITTED => 'READ UNCOMMITTED',
            TransactionIsolationLevel::READ_COMMITTED => 'READ COMMITTED',
            TransactionIsolationLevel::REPEATABLE_READ,
            TransactionIsolationLevel::SERIALIZABLE => 'SERIALIZABLE',
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(1)';
    }

    /**
     * {@inheritDoc}
     */
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(10)';
    }

    /**
     * {@inheritDoc}
     */
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(20)';
    }

    /**
     * {@inheritDoc}
     */
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(5)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'VARCHAR2');
        }

        return sprintf('VARCHAR2(%d)', $length);
    }

    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'RAW');
        }

        return sprintf('RAW(%d)', $length);
    }

    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return $this->getBinaryTypeDeclarationSQLSnippet($length);
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'CLOB';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListDatabasesSQL(): string
    {
        return 'SELECT username FROM all_users';
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListSequencesSQL(string $database): string
    {
        return 'SELECT SEQUENCE_NAME, MIN_VALUE, INCREMENT_BY FROM SYS.ALL_SEQUENCES WHERE SEQUENCE_OWNER = '
            . $this->quoteStringLiteral(
                $this->normalizeIdentifier($database)->getName(),
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(OptionallyQualifiedName $tableName, array $columns, array $parameters): array
    {
        $sql = parent::_getCreateTableSQL($tableName, $columns, $parameters);

        foreach ($columns as $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence']);
            }

            if (
                ! isset($column['autoincrement']) || $column['autoincrement'] === false
            ) {
                continue;
            }

            $sql = array_merge($sql, $this->getCreateAutoincrementSql($tableName, $column['name']));
        }

        foreach ($parameters['indexes'] as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableName->toSQL($this));
        }

        return $sql;
    }

    public function getCreateIndexSQL(Index $index, string $table): string
    {
        $this->ensureIndexHasNoColumnLengths($index);
        $this->ensureIndexIsNotFulltext($index);
        $this->ensureIndexIsNotSpatial($index);
        $this->ensureIndexIsNotClustered($index);
        $this->ensureIndexIsNotPartial($index);

        return parent::getCreateIndexSQL($index, $table);
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListViewsSQL(string $database): string
    {
        return 'SELECT view_name, text FROM sys.user_views';
    }

    /** @return list<string> */
    private function getCreateAutoincrementSql(OptionallyQualifiedName $tableName, UnqualifiedName $columnName): array
    {
        if ($tableName->getQualifier() !== null) {
            throw UnsupportedName::fromQualifiedName($tableName, __METHOD__);
        }

        $sql = [];

        $triggerName  = $this->generateAutoincrementTriggerName($tableName->getUnqualifiedName());
        $sequenceName = $this->generateAutoincrementSequenceName($tableName);
        $sequence     = new Sequence($sequenceName->toString());
        $sql[]        = $this->getCreateSequenceSQL($sequence);

        $sql[] = sprintf(
            <<<'SQL'
CREATE TRIGGER %1$s
   BEFORE INSERT
   ON %2$s
   FOR EACH ROW
DECLARE
   last_Sequence NUMBER;
   last_InsertID NUMBER;
BEGIN
   IF (:NEW.%3$s IS NULL OR :NEW.%3$s = 0) THEN
      SELECT %4$s.NEXTVAL INTO :NEW.%3$s FROM DUAL;
   ELSE
      SELECT NVL(Last_Number, 0) INTO last_Sequence
        FROM USER_SEQUENCES
       WHERE Sequence_Name = %5$s;
      SELECT :NEW.%3$s INTO last_InsertID FROM DUAL;
      WHILE (last_InsertID > last_Sequence) LOOP
         SELECT %4$s.NEXTVAL INTO last_Sequence FROM DUAL;
      END LOOP;
   END IF;
END;
SQL,
            $triggerName->toSQL($this),
            $tableName->toSQL($this),
            $columnName->toSQL($this),
            $sequenceName->toSQL($this),
            $this->quoteStringLiteral(
                $sequenceName->getUnqualifiedName()->toNormalizedValue(
                    $this->getUnquotedIdentifierFolding(),
                ),
            ),
        );

        return $sql;
    }

    /**
     * @internal The method should be only used from within the OracleSchemaManager class hierarchy.
     *
     * Returns the SQL statements to drop the autoincrement for the given table name.
     *
     * @param OptionallyQualifiedName $tableName The table name to drop the autoincrement for.
     *
     * @return string[]
     */
    public function getDropAutoincrementSql(OptionallyQualifiedName $tableName): array
    {
        if ($tableName->getQualifier() !== null) {
            throw UnsupportedName::fromQualifiedName($tableName, __METHOD__);
        }

        $triggerName  = $this->generateAutoincrementTriggerName($tableName->getUnqualifiedName());
        $sequenceName = $this->generateAutoincrementSequenceName($tableName);

        return [
            'DROP TRIGGER ' . $triggerName->toSQL($this),
            $this->getDropSequenceSQL($sequenceName->toSQL($this)),
        ];
    }

    /**
     * Normalizes the given identifier.
     *
     * Uppercases the given identifier if it is not quoted by intention
     * to reflect Oracle's internal auto uppercasing strategy of unquoted identifiers.
     *
     * @param string $name The identifier to normalize.
     */
    private function normalizeIdentifier(string $name): Identifier
    {
        $identifier = new Identifier($name);

        return $identifier->isQuoted() ? $identifier : new Identifier(strtoupper($name));
    }

    /**
     * Adds suffix to identifier,
     *
     * if the new string exceeds max identifier length,
     * keeps $suffix, cuts from $identifier as much as the part exceeding.
     *
     * @param non-empty-string $suffix
     */
    private function addSuffix(Name\Identifier $identifier, string $suffix): Name\Identifier
    {
        $prefix = substr(
            $identifier->toNormalizedValue(
                $this->getUnquotedIdentifierFolding(),
            ),
            0,
            $this->getMaxIdentifierLength() - strlen($suffix),
        );

        return Name\Identifier::quoted($prefix . $suffix);
    }

    /**
     * Returns the autoincrement trigger name for the given table name.
     */
    private function generateAutoincrementTriggerName(Name\Identifier $tableName): UnqualifiedName
    {
        return new UnqualifiedName($this->addSuffix($tableName, '_AI_PK'));
    }

    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $this->ensurePrimaryKeyConstraintIsClustered($constraint);

        return parent::getPrimaryKeyConstraintDeclarationSQL($constraint);
    }

    protected function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql = '';

        $onDeleteAction = $foreignKey->getOnDeleteAction();
        if ($onDeleteAction !== ReferentialAction::NO_ACTION) {
            $sql = ' ON DELETE ' . $this->getForeignKeyReferentialActionSQL($onDeleteAction);
        }

        $deferrability = $foreignKey->getDeferrability();
        if ($deferrability !== Deferrability::NOT_DEFERRABLE) {
            $sql = ' ' . $deferrability->toSQL();
        }

        return $sql;
    }

    protected function getForeignKeyReferentialActionSQL(ReferentialAction $action): string
    {
        return match ($action) {
            ReferentialAction::CASCADE,
            ReferentialAction::SET_NULL => parent::getForeignKeyReferentialActionSQL($action),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported foreign key action "%s".', $action->value),
            ),
        };
    }

    public function getCreateDatabaseSQL(string $name): string
    {
        return 'CREATE USER ' . $name;
    }

    public function getDropDatabaseSQL(string $name): string
    {
        return 'DROP USER ' . $name . ' CASCADE';
    }

    /**
     * {@inheritDoc}
     */
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql          = [];
        $commentsSQL  = [];
        $addColumnSQL = [];

        $tableName    = $diff->getOldTable()->getObjectName();
        $tableNameSQL = $tableName->toSQL($this);

        $droppedPrimaryKeyConstraint = $diff->getDroppedPrimaryKeyConstraint();

        if ($droppedPrimaryKeyConstraint !== null) {
            $constraintName = $droppedPrimaryKeyConstraint->getObjectName();

            if ($constraintName === null) {
                throw UnspecifiedConstraintName::new();
            }

            $sql[] = $this->getDropConstraintSQL($constraintName->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getAddedColumns() as $column) {
            $addColumnSQL[] = $this->getColumnDeclarationSQL($column->toArray());

            $comment = $column->getComment();

            if ($comment === '') {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $tableNameSQL,
                $column->getObjectName()->toSQL($this),
                $comment,
            );
        }

        if (count($addColumnSQL) > 0) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ADD (' . implode(', ', $addColumnSQL) . ')';
        }

        $modifyColumnSQL = [];
        foreach ($diff->getChangedColumns() as $columnDiff) {
            $newColumn = $columnDiff->getNewColumn();
            $oldColumn = $columnDiff->getOldColumn();

            // Column names in Oracle are case insensitive and automatically uppercased on the server.
            if ($columnDiff->hasNameChanged()) {
                $newColumnName = $newColumn->getObjectName()->toSQL($this);
                $oldColumnName = $oldColumn->getObjectName()->toSQL($this);

                $sql = array_merge(
                    $sql,
                    $this->getRenameColumnSQL($tableNameSQL, $oldColumnName, $newColumnName),
                );
            }

            $countChangedProperties = $columnDiff->countChangedProperties();
            // Do not generate column alteration clause if type is binary and only fixed property has changed.
            // Oracle only supports binary type columns with variable length.
            // Avoids unnecessary table alteration statements.
            if (
                $newColumn->getType() instanceof BinaryType &&
                $columnDiff->hasFixedChanged() &&
                $countChangedProperties === 1
            ) {
                continue;
            }

            $columnHasChangedComment = $columnDiff->hasCommentChanged();

            /**
             * Do not add query part if only comment has changed
             */
            if ($countChangedProperties > ($columnHasChangedComment ? 1 : 0)) {
                $newColumnProperties = $newColumn->toArray();

                $oldSQL = $this->getColumnDeclarationSQL($oldColumn->toArray());
                $newSQL = $this->getColumnDeclarationSQL($newColumnProperties);

                if ($newSQL !== $oldSQL) {
                    if (! $columnDiff->hasNotNullChanged()) {
                        unset($newColumnProperties['notnull']);
                        $newSQL = $this->getColumnDeclarationSQL($newColumnProperties);
                    }

                    $modifyColumnSQL[] = $newSQL;
                }
            }

            if (! $columnDiff->hasCommentChanged()) {
                continue;
            }

            $commentsSQL[] = $this->getCommentOnColumnSQL(
                $tableNameSQL,
                $newColumn->getObjectName()->toSQL($this),
                $newColumn->getComment(),
            );
        }

        if (count($modifyColumnSQL) > 0) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' MODIFY (' . implode(', ', $modifyColumnSQL) . ')';
        }

        $dropColumnSQL = [];
        foreach ($diff->getDroppedColumns() as $column) {
            $dropColumnSQL[] = $column->getObjectName()->toSQL($this);
        }

        if (count($dropColumnSQL) > 0) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' DROP (' . implode(', ', $dropColumnSQL) . ')';
        }

        $addedPrimaryKeyConstraint = $diff->getAddedPrimaryKeyConstraint();

        if ($addedPrimaryKeyConstraint !== null) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' ADD '
                . $this->getPrimaryKeyConstraintDeclarationSQL($addedPrimaryKeyConstraint);
        }

        return array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $sql,
            $commentsSQL,
            $this->getPostAlterTableIndexForeignKeySQL($diff),
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getColumnDeclarationSQL(array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $column['columnDefinition'];
        } else {
            $default = $this->getDefaultValueDeclarationSQL($column);

            $notnull = '';

            if (isset($column['notnull'])) {
                $notnull = $column['notnull'] ? ' NOT NULL' : ' NULL';
            }

            $typeDecl    = $column['type']->getSQLDeclaration($column, $this);
            $declaration = $typeDecl . $default . $notnull;
        }

        return $column['name']->toSQL($this) . ' ' . $declaration;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        if (str_contains($tableName, '.')) {
            [$schema]     = explode('.', $tableName);
            $oldIndexName = $schema . '.' . $oldIndexName;
        }

        return ['ALTER INDEX ' . $oldIndexName . ' RENAME TO ' . $index->getObjectName()->toSQL($this)];
    }

    private function generateAutoincrementSequenceName(OptionallyQualifiedName $tableName): OptionallyQualifiedName
    {
        return new OptionallyQualifiedName(
            $this->addSuffix($tableName->getUnqualifiedName(), '_SEQ'),
            $tableName->getQualifier(),
        );
    }

    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    protected function doModifyLimitQuery(string $query, ?int $limit, int $offset): string
    {
        if ($offset > 0) {
            $query .= sprintf(' OFFSET %d ROWS', $offset);
        }

        if ($limit !== null) {
            $query .= sprintf(' FETCH NEXT %d ROWS ONLY', $limit);
        }

        return $query;
    }

    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE GLOBAL TEMPORARY TABLE';
    }

    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:sP';
    }

    public function getDateFormatString(): string
    {
        return 'Y-m-d 00:00:00';
    }

    public function getTimeFormatString(): string
    {
        return '1900-01-01 H:i:s';
    }

    public function getMaxIdentifierLength(): int
    {
        return 128;
    }

    public function supportsSequences(): bool
    {
        return true;
    }

    public function supportsReleaseSavepoints(): bool
    {
        return false;
    }

    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'TRUNCATE TABLE ' . $tableIdentifier->getObjectName()->toSQL($this);
    }

    public function getDummySelectSQL(string $expression = '1'): string
    {
        return sprintf('SELECT %s FROM DUAL', $expression);
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        $this->doctrineTypeMapping = [
            'binary_double'  => Types::FLOAT,
            'binary_float'   => Types::FLOAT,
            'binary_integer' => Types::BOOLEAN,
            'blob'           => Types::BLOB,
            'char'           => Types::STRING,
            'clob'           => Types::TEXT,
            'date'           => Types::DATE_MUTABLE,
            'float'          => Types::FLOAT,
            'integer'        => Types::INTEGER,
            'long'           => Types::STRING,
            'long raw'       => Types::BLOB,
            'nchar'          => Types::STRING,
            'nclob'          => Types::TEXT,
            'number'         => Types::INTEGER,
            'nvarchar2'      => Types::STRING,
            'pls_integer'    => Types::BOOLEAN,
            'raw'            => Types::BINARY,
            'real'           => Types::SMALLFLOAT,
            'rowid'          => Types::STRING,
            'timestamp'      => Types::DATETIME_MUTABLE,
            'timestamptz'    => Types::DATETIMETZ_MUTABLE,
            'urowid'         => Types::STRING,
            'varchar'        => Types::STRING,
            'varchar2'       => Types::STRING,
        ];
    }

    public function releaseSavePoint(string $savepoint): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'BLOB';
    }

    public function createSchemaManager(Connection $connection): OracleSchemaManager
    {
        return new OracleSchemaManager($connection, $this);
    }
}

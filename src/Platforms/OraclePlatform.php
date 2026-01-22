<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Oracle\OracleMetadataProvider;
use Doctrine\DBAL\Schema\Exception\UnspecifiedConstraintName;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
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
use Override;

use function array_merge;
use function count;
use function implode;
use function sprintf;
use function strlen;
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

    #[Override]
    public function getSubstringExpression(string $string, string $start, ?string $length = null): string
    {
        if ($length === null) {
            return sprintf('SUBSTR(%s, %s)', $string, $start);
        }

        return sprintf('SUBSTR(%s, %s, %s)', $string, $start, $length);
    }

    #[Override]
    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        if ($start === null) {
            return sprintf('INSTR(%s, %s)', $string, $substring);
        }

        return sprintf('INSTR(%s, %s, %s)', $string, $substring, $start);
    }

    #[Override]
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

    #[Override]
    public function getDateDiffExpression(string $date1, string $date2): string
    {
        return sprintf('TRUNC(%s) - TRUNC(%s)', $date1, $date2);
    }

    #[Override]
    public function getBitAndComparisonExpression(string $value1, string $value2): string
    {
        return 'BITAND(' . $value1 . ', ' . $value2 . ')';
    }

    #[Override]
    public function getCurrentDatabaseExpression(): string
    {
        return "SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA')";
    }

    #[Override]
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
    #[Override]
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        return 'CREATE SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               $this->getSequenceCacheSQL($sequence->getCacheSize());
    }

    #[Override]
    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getObjectName()->toSQL($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize()
               . $this->getSequenceCacheSQL($sequence->getCacheSize());
    }

    /**
     * Cache definition for sequences
     */
    private function getSequenceCacheSQL(?int $cacheSize): string
    {
        if ($cacheSize === 0 || $cacheSize === 1) {
            return ' NOCACHE';
        }

        if ($cacheSize > 1) {
            return ' CACHE ' . $cacheSize;
        }

        return '';
    }

    #[Override]
    public function getSequenceNextValSQL(string $sequenceName): string
    {
        $parsedName = $this->parseUnqualifiedName($sequenceName);

        return sprintf('SELECT %s.nextval FROM DUAL', $parsedName->toSQL($this));
    }

    #[Override]
    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        return 'SET TRANSACTION ISOLATION LEVEL ' . $this->_getTransactionIsolationLevelSQL($level);
    }

    #[Override]
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
    #[Override]
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(1)';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(10)';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(20)';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return 'NUMBER(5)';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0)';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getDateTimeTzTypeDeclarationSQL(array $column): string
    {
        return 'TIMESTAMP(0) WITH TIME ZONE';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getDateTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        return 'DATE';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    #[Override]
    protected function getVarcharTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'VARCHAR2');
        }

        return sprintf('VARCHAR2(%d)', $length);
    }

    #[Override]
    protected function getBinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        if ($length === null) {
            throw ColumnLengthRequired::new($this, 'RAW');
        }

        return sprintf('RAW(%d)', $length);
    }

    #[Override]
    protected function getVarbinaryTypeDeclarationSQLSnippet(?int $length): string
    {
        return $this->getBinaryTypeDeclarationSQLSnippet($length);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getClobTypeDeclarationSQL(array $column): string
    {
        return 'CLOB';
    }

    #[Override]
    public function getCurrentTimeSQL(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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

    #[Override]
    public function getCreateIndexSQL(Index $index, string $tableName): string
    {
        $this->ensureIndexHasNoColumnLengths($index);
        $this->ensureIndexIsNotFulltext($index);
        $this->ensureIndexIsNotSpatial($index);
        $this->ensureIndexIsNotClustered($index);
        $this->ensureIndexIsNotPartial($index);

        return parent::getCreateIndexSQL($index, $tableName);
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

        $sequence = Sequence::editor()
            ->setName($sequenceName)
            ->create();

        $sql[] = $this->getCreateSequenceSQL($sequence);

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

    #[Override]
    public function getDropForeignKeySQL(string $constraintName, string $tableName): string
    {
        return $this->getDropConstraintSQL($constraintName, $tableName);
    }

    #[Override]
    protected function getPrimaryKeyConstraintDeclarationSQL(PrimaryKeyConstraint $constraint): string
    {
        $this->ensurePrimaryKeyConstraintIsClustered($constraint);

        return parent::getPrimaryKeyConstraintDeclarationSQL($constraint);
    }

    #[Override]
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

    #[Override]
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

    #[Override]
    public function getCreateDatabaseSQL(string $databaseName): string
    {
        $parsedName = $this->parseUnqualifiedName($databaseName);

        return sprintf('CREATE USER %s', $parsedName->toSQL($this));
    }

    #[Override]
    public function getDropDatabaseSQL(string $databaseName): string
    {
        $parsedName = $this->parseUnqualifiedName($databaseName);

        return sprintf('DROP USER %s CASCADE', $parsedName->toSQL($this));
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getAlterTableSQL(TableDiff $diff): array
    {
        $sql          = [];
        $commentsSQL  = [];
        $addColumnSQL = [];

        $tableName    = $diff->getOldTable()->getObjectName();
        $tableNameSQL = $tableName->toSQL($this);

        foreach ($diff->getDroppedForeignKeyConstraintNames() as $constraintName) {
            $sql[] = $this->getDropForeignKeySQL($constraintName->toSQL($this), $tableNameSQL);
        }

        foreach ($diff->getDroppedIndexes() as $index) {
            $sql[] = $this->getDropIndexSQL($index->getObjectName()->toSQL($this), $tableNameSQL);
        }

        $droppedPrimaryKeyConstraint = $diff->getDroppedPrimaryKeyConstraint();

        if ($droppedPrimaryKeyConstraint !== null) {
            $constraintName = $droppedPrimaryKeyConstraint->getObjectName();

            if ($constraintName === null) {
                throw UnspecifiedConstraintName::forPrimaryKeyConstraint();
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

        foreach ($diff->getAddedForeignKeys() as $foreignKey) {
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableNameSQL);
        }

        foreach ($diff->getAddedIndexes() as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $tableNameSQL);
        }

        foreach ($diff->getRenamedIndexes() as $oldIndexName => $index) {
            $sql = array_merge(
                $sql,
                $this->getRenameIndexSQL($oldIndexName, $index, $tableNameSQL),
            );
        }

        return array_merge($sql, $commentsSQL);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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
    #[Override]
    protected function getRenameIndexSQL(string $oldIndexName, Index $index, string $tableName): array
    {
        $parsedOldIndexName = $this->parseUnqualifiedName($oldIndexName);
        $parsedTableName    = $this->parseOptionallyQualifiedName($tableName);

        return [
            sprintf(
                'ALTER INDEX %s RENAME TO %s',
                $this->deriveQualifier($parsedOldIndexName, $parsedTableName)->toSQL($this),
                $index->getObjectName()->toSQL($this),
            ),
        ];
    }

    private function generateAutoincrementSequenceName(OptionallyQualifiedName $tableName): OptionallyQualifiedName
    {
        return new OptionallyQualifiedName(
            $this->addSuffix($tableName->getUnqualifiedName(), '_SEQ'),
            $tableName->getQualifier(),
        );
    }

    #[Override]
    protected function supportsCommentOnStatement(): bool
    {
        return true;
    }

    #[Override]
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

    #[Override]
    public function getCreateTemporaryTableSnippetSQL(): string
    {
        return 'CREATE GLOBAL TEMPORARY TABLE';
    }

    #[Override]
    public function getDateTimeTzFormatString(): string
    {
        return 'Y-m-d H:i:sP';
    }

    #[Override]
    public function getDateFormatString(): string
    {
        return 'Y-m-d 00:00:00';
    }

    #[Override]
    public function getTimeFormatString(): string
    {
        return '1900-01-01 H:i:s';
    }

    #[Override]
    public function getMaxIdentifierLength(): int
    {
        return 128;
    }

    #[Override]
    public function supportsSequences(): bool
    {
        return true;
    }

    #[Override]
    public function supportsReleaseSavepoints(): bool
    {
        return false;
    }

    #[Override]
    public function getTruncateTableSQL(string $tableName, bool $cascade = false): string
    {
        $parsedName = $this->parseOptionallyQualifiedName($tableName);

        return sprintf('TRUNCATE TABLE %s', $parsedName->toSQL($this));
    }

    #[Override]
    public function getDummySelectSQL(string $expression = '1'): string
    {
        return sprintf('SELECT %s FROM DUAL', $expression);
    }

    #[Override]
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

    #[Override]
    public function releaseSavePoint(string $savepoint): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'BLOB';
    }

    #[Override]
    public function createMetadataProvider(Connection $connection): OracleMetadataProvider
    {
        return new OracleMetadataProvider($connection, $this);
    }

    #[Override]
    public function createSchemaManager(Connection $connection): OracleSchemaManager
    {
        return new OracleSchemaManager($connection, $this);
    }
}

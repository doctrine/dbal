<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\OracleKeywords;
use Doctrine\DBAL\Platforms\Oracle\OracleMetadataProvider;
use Doctrine\DBAL\Platforms\Oracle\SdoGeometry;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\OracleSchemaManager;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\SpatialRelation;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\GeographyType;
use Doctrine\DBAL\Types\GeometryType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;

use function array_merge;
use function count;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_split;
use function strlen;
use function strtoupper;
use function substr;

/**
 * OraclePlatform.
 */
class OraclePlatform extends AbstractPlatform
{
    /**
     * The distance under which Oracle Spatial considers two points equal, in the unit of the coordinates (or in meters
     * in a geodetic coordinate system): small enough to obtain the results of an exact computation.
     */
    private const SPATIAL_TOLERANCE = '0.000000001';

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

    /** @deprecated */
    public function getCreatePrimaryKeySQL(Index $index, string $table): string
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6867',
            '%s() is deprecated.',
            __METHOD__,
        );

        return 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $index->getQuotedName($this)
            . ' PRIMARY KEY (' . implode(', ', $index->getQuotedColumns($this)) . ')';
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
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' MINVALUE ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               $this->getSequenceCacheSQL($sequence->getCacheSize());
    }

    public function getAlterSequenceSQL(Sequence $sequence): string
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
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
        // the sequences of spatial indexes are named MDRS_<number>$
        return 'SELECT SEQUENCE_NAME, MIN_VALUE, INCREMENT_BY FROM SYS.ALL_SEQUENCES WHERE SEQUENCE_OWNER = '
            . $this->quoteStringLiteral(
                $this->normalizeIdentifier($database)->getName(),
            )
            . " AND SEQUENCE_NAME NOT LIKE 'MDRS\\_%$' ESCAPE '\\'";
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL(string $name, array $columns, array $options = []): array
    {
        $this->validateCreateTableOptions($options, __METHOD__);

        $indexes            = $options['indexes'] ?? [];
        $options['indexes'] = [];
        $sql                = parent::_getCreateTableSQL($name, $columns, $options);

        foreach ($columns as $column) {
            if (isset($column['sequence'])) {
                $sql[] = $this->getCreateSequenceSQL($column['sequence']);
            }

            if (
                isset($column['autoincrement']) && $column['autoincrement'] !== false
            ) {
                $sql = array_merge($sql, $this->getCreateAutoincrementSql($column['name'], $name));
            }
        }

        foreach ($columns as $column) {
            if (! $this->isSpatialColumn($column)) {
                continue;
            }

            $sql[] = $this->getGeometryMetadataSQL($name, $column);
        }

        foreach ($indexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $name);
        }

        return $sql;
    }

    /** @internal The method should be only used from within the {@see AbstractSchemaManager} class hierarchy. */
    public function getListViewsSQL(string $database): string
    {
        return 'SELECT view_name, text FROM sys.user_views';
    }

    /**
     * @internal The method should be only used by the {@see OraclePlatform} class.
     *
     * @return array<int, string>
     */
    protected function getCreateAutoincrementSql(string $name, string $table, int $start = 1): array
    {
        $tableIdentifier   = $this->normalizeIdentifier($table);
        $quotedTableName   = $tableIdentifier->getQuotedName($this);
        $unquotedTableName = $tableIdentifier->getName();

        $nameIdentifier = $this->normalizeIdentifier($name);
        $quotedName     = $nameIdentifier->getQuotedName($this);

        $sql = [];

        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($tableIdentifier);

        $idx = new Index($autoincrementIdentifierName, [$quotedName], true, true);

        $sql[] = sprintf(
            <<<'SQL'
DECLARE
  CONSTRAINTS_COUNT NUMBER;
BEGIN
  SELECT COUNT(CONSTRAINT_NAME) INTO CONSTRAINTS_COUNT
    FROM USER_CONSTRAINTS
   WHERE TABLE_NAME = %s
     AND CONSTRAINT_TYPE = 'P';
  IF CONSTRAINTS_COUNT = 0 THEN
    EXECUTE IMMEDIATE %s;
  END IF;
END;
SQL,
            $this->quoteStringLiteral($unquotedTableName),
            $this->quoteStringLiteral($this->getCreateIndexSQL($idx, $quotedTableName)),
        );

        $sequenceName = $this->getIdentitySequenceName(
            $tableIdentifier->isQuoted() ? $quotedTableName : $unquotedTableName,
        );
        $sequence     = new Sequence($sequenceName, $start);
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
            $autoincrementIdentifierName,
            $quotedTableName,
            $quotedName,
            $sequenceName,
            $this->quoteStringLiteral($sequence->getName()),
        );

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
    public function getDropAutoincrementSql(string $table): array
    {
        $table                       = $this->normalizeIdentifier($table);
        $autoincrementIdentifierName = $this->getAutoincrementIdentifierName($table);
        $identitySequenceName        = $this->getIdentitySequenceName(
            $table->isQuoted() ? $table->getQuotedName($this) : $table->getName(),
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
     */
    private function getAutoincrementIdentifierName(Identifier $table): string
    {
        $identifierName = $this->addSuffix($table->getName(), '_AI_PK');

        return $table->isQuoted()
            ? $this->quoteSingleIdentifier($identifierName)
            : $identifierName;
    }

    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        return $this->getDropConstraintSQL($foreignKey, $table);
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        $sql = '';

        if ($foreignKey->hasOption('onDelete')) {
            $referentialAction = $this->getForeignKeyReferentialActionSQL($foreignKey->getOption('onDelete'));

            if ($referentialAction !== '') {
                $sql .= ' ON DELETE ' . $referentialAction;
            }
        }

        $deferrabilitySQL = $this->getConstraintDeferrabilitySQL($foreignKey);

        if ($deferrabilitySQL !== '') {
            $sql .= $deferrabilitySQL;
        }

        return $sql;
    }

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function getForeignKeyReferentialActionSQL(string $action): string
    {
        $action = strtoupper($action);

        if ($action === 'RESTRICT') {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6707',
                'Relying on automatic conversion of RESTRICT to NO ACTION for Oracle is deprecated.'
                    . ' Use NO ACTION explicitly instead.',
            );
        }

        return match ($action) {
            'RESTRICT',
            'NO ACTION' => '',
            'CASCADE',
            'SET NULL' => $action,
            default => throw new InvalidArgumentException(sprintf('Invalid foreign key action "%s".', $action)),
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
        $sql                 = [];
        $commentsSQL         = [];
        $addColumnSQL        = [];
        $geometryMetadataSQL = [];

        $tableNameSQL = $diff->getOldTable()->getQuotedName($this);

        foreach ($diff->getAddedColumns() as $column) {
            $addColumnSQL[] = $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray(true));
            $comment        = $column->getComment();

            if ($this->isSpatialColumn($column)) {
                $geometryMetadataSQL[] = $this->getGeometryMetadataSQL(
                    $tableNameSQL,
                    ['name' => $column->getQuotedName($this)] + $column->toArray(true),
                );
            }

            if ($comment !== '') {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $tableNameSQL,
                    $column->getQuotedName($this),
                    $comment,
                );
            }
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
                $newColumnName = $newColumn->getQuotedName($this);
                $oldColumnName = $oldColumn->getQuotedName($this);

                $sql = array_merge(
                    $sql,
                    $this->getRenameColumnSQL($tableNameSQL, $oldColumnName, $newColumnName),
                );
            }

            if (
                $this->isSpatialColumn($newColumn)
                && ($columnDiff->hasNameChanged() || $columnDiff->hasTypeChanged())
            ) {
                $geometryMetadataSQL[] = $this->getGeometryMetadataSQL(
                    $tableNameSQL,
                    ['name' => $newColumn->getQuotedName($this)] + $newColumn->toArray(true),
                );
            }

            $countChangedProperties = $columnDiff->countChangedProperties();
            // Do not generate column alteration clause if type is binary and only fixed property has changed.
            // Oracle only supports binary type columns with variable length.
            // Avoids unnecessary table alteration statements.
            if (
                $this->getColumnType($newColumn) instanceof BinaryType &&
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
                $newColumnProperties = $newColumn->toArray(true);

                $oldSQL = $this->getColumnDeclarationSQL('', $oldColumn->toArray(true));
                $newSQL = $this->getColumnDeclarationSQL('', $newColumnProperties);

                if ($newSQL !== $oldSQL) {
                    if (! $columnDiff->hasNotNullChanged()) {
                        unset($newColumnProperties['notnull']);
                        $newSQL = $this->getColumnDeclarationSQL('', $newColumnProperties);
                    }

                    $modifyColumnSQL[] = $newColumn->getQuotedName($this) . $newSQL;
                }
            }

            if ($columnDiff->hasCommentChanged()) {
                $commentsSQL[] = $this->getCommentOnColumnSQL(
                    $tableNameSQL,
                    $newColumn->getQuotedName($this),
                    $newColumn->getComment(),
                );
            }
        }

        if (count($modifyColumnSQL) > 0) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' MODIFY (' . implode(', ', $modifyColumnSQL) . ')';
        }

        $dropColumnSQL = [];
        foreach ($diff->getDroppedColumns() as $column) {
            $dropColumnSQL[] = $column->getQuotedName($this);
        }

        if (count($dropColumnSQL) > 0) {
            $sql[] = 'ALTER TABLE ' . $tableNameSQL . ' DROP (' . implode(', ', $dropColumnSQL) . ')';
        }

        return array_merge(
            $this->getPreAlterTableIndexForeignKeySQL($diff),
            $sql,
            $commentsSQL,
            $geometryMetadataSQL,
            $this->getPostAlterTableIndexForeignKeySQL($diff),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy.
     */
    public function getColumnDeclarationSQL(string $name, array $column): string
    {
        if (isset($column['columnDefinition'])) {
            $declaration = $column['columnDefinition'];
        } else {
            $default = $this->getDefaultValueDeclarationSQL($column);

            $notnull = '';

            if (isset($column['notnull'])) {
                $notnull = $column['notnull'] ? ' NOT NULL' : ' NULL';
            }

            $typeDecl    = $this->getColumnType($column)->getSQLDeclaration($column, $this);
            $declaration = $typeDecl . $default . $notnull;
        }

        return $name . ' ' . $declaration;
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

        return ['ALTER INDEX ' . $oldIndexName . ' RENAME TO ' . $index->getQuotedName($this)];
    }

    /** @internal The method should be only used by the {@see OraclePlatform} class. */
    protected function getIdentitySequenceName(string $tableName): string
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

    /** @internal The method should be only used from within the {@see AbstractPlatform} class hierarchy. */
    public function supportsCommentOnStatement(): bool
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

        return 'TRUNCATE TABLE ' . $tableIdentifier->getQuotedName($this);
    }

    public function getDummySelectSQL(string $expression = '1'): string
    {
        return sprintf('SELECT %s FROM DUAL', $expression);
    }

    /**
     * {@inheritDoc}
     *
     * Requires Oracle Spatial (Locator), which all editions include. The kind, the dimension and the SRID are not
     * enforced.
     */
    public function getGeometryTypeDeclarationSQL(array $column): string
    {
        // the options are only validated, the SRID and the dimension are registered in USER_SDO_GEOM_METADATA
        $this->getGeometryColumnOptions($column);

        return 'SDO_GEOMETRY';
    }

    /**
     * The value is a JSON object of the components of the SDO_GEOMETRY object, which
     * {@see convertGeometryToDatabaseValueSQL()} builds: SDO_GEOMETRY objects cannot be bound.
     */
    public function convertGeometryToDatabaseValue(Geometry $geometry): string
    {
        return SdoGeometry::encode($geometry);
    }

    public function convertGeometryToDatabaseValueSQL(string $sqlExpr): string
    {
        // the scalar subquery references the parameter once, CASE preserves NULL values
        return '(SELECT CASE WHEN v IS NULL THEN NULL ELSE SDO_GEOMETRY('
            . "JSON_VALUE(v, '$.t' RETURNING NUMBER ERROR ON ERROR), "
            . "JSON_VALUE(v, '$.s' RETURNING NUMBER ERROR ON ERROR), "
            . 'NULL, '
            . 'CAST(MULTISET(SELECT n FROM JSON_TABLE('
            . "v, '$.e[*]' ERROR ON ERROR COLUMNS (i FOR ORDINALITY, n NUMBER PATH '$')"
            . ') ORDER BY i) AS SDO_ELEM_INFO_ARRAY), '
            . 'CAST(MULTISET(SELECT n FROM JSON_TABLE('
            . "v, '$.o[*]' ERROR ON ERROR COLUMNS (i FOR ORDINALITY, n NUMBER PATH '$')"
            . ') ORDER BY i) AS SDO_ORDINATE_ARRAY)'
            . ') END FROM (SELECT TO_CLOB(' . $sqlExpr . ') AS v FROM DUAL))';
    }

    /**
     * Selects the components of the SDO_GEOMETRY object as a JSON object: SDO_GEOMETRY objects cannot be fetched. The
     * scalar subquery references the expression once, which may contain parameters.
     */
    public function convertGeometryToPHPValueSQL(string $sqlExpr): string
    {
        return '(SELECT CASE WHEN s.g IS NULL THEN NULL ELSE JSON_OBJECT('
            . "'t' VALUE s.g.SDO_GTYPE, "
            . "'s' VALUE s.g.SDO_SRID, "
            . "'p' VALUE CASE WHEN s.g.SDO_POINT IS NULL THEN NULL"
            . ' ELSE JSON_ARRAY(s.g.SDO_POINT.X, s.g.SDO_POINT.Y, s.g.SDO_POINT.Z NULL ON NULL) END FORMAT JSON, '
            . "'e' VALUE (SELECT JSON_ARRAYAGG(c ORDER BY r NULL ON NULL RETURNING CLOB)"
            . ' FROM (SELECT COLUMN_VALUE c, ROWNUM r FROM TABLE(s.g.SDO_ELEM_INFO))) FORMAT JSON, '
            . "'o' VALUE (SELECT JSON_ARRAYAGG(c ORDER BY r NULL ON NULL RETURNING CLOB)"
            . ' FROM (SELECT COLUMN_VALUE c, ROWNUM r FROM TABLE(s.g.SDO_ORDINATES))) FORMAT JSON'
            . ' NULL ON NULL RETURNING CLOB) END FROM (SELECT ' . $sqlExpr . ' g FROM DUAL) s)';
    }

    public function convertGeometryToPHPValue(mixed $value): Geometry
    {
        if (! is_string($value)) {
            throw InvalidGeometry::malformedSdoGeometry('expected a string');
        }

        return SdoGeometry::decode($value);
    }

    /**
     * The value is bound as a string: pdo_oci cannot bind LOBs in expressions.
     */
    public function getGeometryBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }

    /**
     * Spatial indexes require the geometry column to be registered in USER_SDO_GEOM_METADATA, which DBAL does when it
     * creates the column.
     */
    public function getCreateIndexSQL(Index $index, string $table): string
    {
        if ($index->getType() !== IndexType::SPATIAL) {
            return parent::getCreateIndexSQL($index, $table);
        }

        return 'CREATE INDEX ' . $index->getQuotedName($this) . ' ON ' . $table
            . ' (' . implode(', ', $index->getQuotedColumns($this)) . ') INDEXTYPE IS MDSYS.SPATIAL_INDEX_V2';
    }

    public function getSpatialRelationExpression(
        SpatialRelation $relation,
        string $geometry1,
        string $geometry2,
    ): string {
        $mask = match ($relation) {
            SpatialRelation::CONTAINS => 'CONTAINS+COVERS+EQUAL',
            SpatialRelation::DISJOINT => 'DISJOINT',
            SpatialRelation::EQUALS => 'EQUAL',
            SpatialRelation::INTERSECTS => 'ANYINTERACT',
            SpatialRelation::TOUCHES => 'TOUCH+ON',
            SpatialRelation::WITHIN => 'INSIDE+COVEREDBY+EQUAL',
            SpatialRelation::CROSSES,
            SpatialRelation::OVERLAPS => null,
        };

        if ($mask === null) {
            return $this->getSpatialCrossesOrOverlapsExpression(
                $relation === SpatialRelation::CROSSES,
                $geometry1,
                $geometry2,
            );
        }

        // RELATE() returns FALSE, or the relationships of the mask that hold (TRUE for ANYINTERACT)
        return sprintf(
            "DECODE(SDO_GEOM.RELATE(SDO_CS.MAKE_2D(%s), '%s', SDO_CS.MAKE_2D(%s), %s), 'FALSE', 0, NULL, NULL, 1)",
            $geometry1,
            $mask,
            $geometry2,
            self::SPATIAL_TOLERANCE,
        );
    }

    public function getSpatialDistanceExpression(string $geometry1, string $geometry2): string
    {
        return sprintf(
            'SDO_GEOM.SDO_DISTANCE(%s, %s, %s)',
            $this->getPlanarGeometrySQL($geometry1),
            $this->getPlanarGeometrySQL($geometry2),
            self::SPATIAL_TOLERANCE,
        );
    }

    public function getSpatialAreaExpression(string $geometry): string
    {
        return sprintf(
            'SDO_GEOM.SDO_AREA(%s, %s)',
            $this->getPlanarGeometrySQL($geometry),
            self::SPATIAL_TOLERANCE,
        );
    }

    public function getSpatialLengthExpression(string $geometry): string
    {
        return sprintf(
            'SDO_GEOM.SDO_LENGTH(%s, %s)',
            $this->getPlanarGeometrySQL($geometry),
            self::SPATIAL_TOLERANCE,
        );
    }

    public function getSpatialXExpression(string $point): string
    {
        return '(SELECT v.X FROM TABLE(SDO_UTIL.GETVERTICES(' . $point . ')) v WHERE v.ID = 1)';
    }

    public function getSpatialYExpression(string $point): string
    {
        return '(SELECT v.Y FROM TABLE(SDO_UTIL.GETVERTICES(' . $point . ')) v WHERE v.ID = 1)';
    }

    public function getSpatialSridExpression(string $geometry): string
    {
        // the SRID 0 is stored as NULL, the scalar subquery references the geometry once
        return '(SELECT CASE WHEN s.g IS NULL THEN NULL ELSE NVL(s.g.SDO_SRID, 0) END'
            . ' FROM (SELECT ' . $geometry . ' g FROM DUAL) s)';
    }

    /**
     * Oracle reports both crossing and overlapping geometries as overlapping: they are told apart by the dimensions of
     * the geometries and, for two line strings, of their intersection.
     */
    private function getSpatialCrossesOrOverlapsExpression(bool $crosses, string $geometry1, string $geometry2): string
    {
        $dimension = static fn (string $geometry): string => sprintf(
            'CASE WHEN SDO_GEOM.SDO_AREA(%1$s, %2$s) > 0 THEN 2'
                . ' WHEN SDO_GEOM.SDO_LENGTH(%1$s, %2$s) > 0 THEN 1 ELSE 0 END',
            $geometry,
            self::SPATIAL_TOLERANCE,
        );

        // the scalar subquery references the geometries once
        return sprintf(
            '(SELECT CASE WHEN r.v IS NULL THEN NULL'
                . " WHEN r.v NOT IN ('OVERLAPBDYDISJOINT', 'OVERLAPBDYINTERSECT') THEN 0"
                . ' WHEN %1$s <> %2$s THEN %3$d'
                . ' WHEN %1$s <> 1 THEN %4$d'
                . ' WHEN SDO_GEOM.SDO_LENGTH(SDO_GEOM.SDO_INTERSECTION(r.a, r.b, %5$s), %5$s) > 0 THEN %4$d'
                . ' ELSE %3$d END'
                . ' FROM (SELECT g.a, g.b, SDO_GEOM.RELATE(g.a, \'DETERMINE\', g.b, %5$s) v'
                . ' FROM (SELECT SDO_CS.MAKE_2D(%6$s) a, SDO_CS.MAKE_2D(%7$s) b FROM DUAL) g) r)',
            $dimension('r.a'),
            $dimension('r.b'),
            // geometries of different dimensions, or line strings intersecting at points, cross each other
            $crosses ? 1 : 0,
            // other geometries of the same dimension overlap each other
            $crosses ? 0 : 1,
            self::SPATIAL_TOLERANCE,
            $geometry1,
            $geometry2,
        );
    }

    /**
     * Registers a geometry column in USER_SDO_GEOM_METADATA, as spatial indexes require: with the SRID and the
     * coordinate dimension of the column, and the bounds of the longitudes and latitudes if the SRID is geodetic. The
     * registration of a former column with the same name is replaced, since dropping a table does not remove it.
     *
     * @param array<string, mixed> $column
     */
    private function getGeometryMetadataSQL(string $table, array $column): string
    {
        if ($this->getColumnType($column) instanceof GeographyType) {
            $column = self::getGeographicColumn($column);
        }

        [, $dimension, $srid] = $this->getGeometryColumnOptions($column);

        $tolerance = self::SPATIAL_TOLERANCE;
        $geodetic  = $planar = [];

        foreach (str_split(($dimension ?? Dimension::XY)->value) as $name) {
            [$min, $max] = match ($name) {
                'X' => ['-180', '180'],
                'Y' => ['-90', '90'],
                default => ['-1E15', '1E15'],
            };

            $geodetic[] = sprintf("SDO_DIM_ELEMENT('%s', %s, %s, %s)", $name, $min, $max, $tolerance);
            $planar[]   = sprintf("SDO_DIM_ELEMENT('%s', -1E15, 1E15, %s)", $name, $tolerance);
        }

        return sprintf(
            <<<'SQL'
            BEGIN
              DELETE FROM USER_SDO_GEOM_METADATA WHERE TABLE_NAME = %1$s AND COLUMN_NAME = %2$s;
              INSERT INTO USER_SDO_GEOM_METADATA (TABLE_NAME, COLUMN_NAME, DIMINFO, SRID)
              SELECT %1$s, %2$s, CASE WHEN COUNT(*) > 0 THEN SDO_DIM_ARRAY(%3$s) ELSE SDO_DIM_ARRAY(%4$s) END, %5$s
              FROM MDSYS.SDO_COORD_REF_SYS WHERE SRID = %5$s AND COORD_REF_SYS_KIND LIKE 'GEOGRAPHIC%%';
            END;
            SQL,
            $this->quoteStringLiteral($this->getGeometryMetadataName($table)),
            $this->quoteStringLiteral($this->getGeometryMetadataName($column['name'])),
            implode(', ', $geodetic),
            implode(', ', $planar),
            $srid === null || $srid === 0 ? 'NULL' : $srid,
        );
    }

    /** @param array<string, mixed>|Column $column */
    private function isSpatialColumn(array|Column $column): bool
    {
        $type = $this->getColumnType($column);

        return $type instanceof GeometryType || $type instanceof GeographyType;
    }

    /**
     * Returns the name of a table or a column as USER_SDO_GEOM_METADATA stores it: between double quotes if it is not
     * a valid unquoted identifier.
     */
    private function getGeometryMetadataName(string $name): string
    {
        $name = $this->normalizeIdentifier($name)->getName();

        return preg_match('/^[A-Z][A-Z0-9_$#]*$/', $name) === 1 ? $name : '"' . $name . '"';
    }

    /**
     * Removes the Z and M coordinates and the SRID of the geometry, so that Oracle computes in the plane of the
     * coordinates rather than on the ellipsoid of a geodetic coordinate system. The scalar subquery references the
     * geometry once.
     */
    private function getPlanarGeometrySQL(string $geometry): string
    {
        return '(SELECT CASE WHEN p.g IS NULL THEN NULL'
            . ' ELSE SDO_GEOMETRY(p.g.SDO_GTYPE, NULL, p.g.SDO_POINT, p.g.SDO_ELEM_INFO, p.g.SDO_ORDINATES) END'
            . ' FROM (SELECT SDO_CS.MAKE_2D(' . $geometry . ') g FROM DUAL) p)';
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
            'sdo_geometry'   => Types::GEOMETRY,
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

    /** @deprecated */
    protected function createReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

        return new OracleKeywords();
    }

    /**
     * {@inheritDoc}
     */
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return 'BLOB';
    }

    public function createMetadataProvider(Connection $connection): OracleMetadataProvider
    {
        return new OracleMetadataProvider($connection, $this);
    }

    public function createSchemaManager(Connection $connection): OracleSchemaManager
    {
        return new OracleSchemaManager($connection, $this);
    }
}

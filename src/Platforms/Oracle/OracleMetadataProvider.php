<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Oracle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\DatabaseMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\SequenceMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;

use function assert;
use function implode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

final readonly class OracleMetadataProvider implements MetadataProvider
{
    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private OraclePlatform $platform)
    {
    }

    /** {@inheritDoc}
     *
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/ALL_USERS.html
     */
    public function getAllDatabaseNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT USERNAME
        FROM ALL_USERS
        ORDER BY USERNAME
        SQL;

        foreach ($this->connection->iterateColumn($sql) as $databaseName) {
            yield new DatabaseMetadataRow($databaseName);
        }
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_TABLES.html
     */
    public function getAllTableNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABLE_NAME
        FROM USER_TABLES
        ORDER BY TABLE_NAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new TableMetadataRow(null, $row[0], []);
        }
    }

    /** {@inheritDoc} */
    public function getTableColumnsForAllTables(): iterable
    {
        return $this->getTableColumns(null);
    }

    /** {@inheritDoc} */
    public function getTableColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableColumns($tableName);
    }

    /**
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_TAB_COLUMNS.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_TABLES.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_COL_COMMENTS.html
     *
     * @return iterable<TableColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getTableColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT C.TABLE_NAME,
                   C.COLUMN_NAME,
                   C.DATA_TYPE,
                   C.DATA_DEFAULT,
                   C.DATA_PRECISION,
                   C.DATA_SCALE,
                   C.CHAR_LENGTH,
                   C.DATA_LENGTH,
                   C.NULLABLE,
                   D.COMMENTS
            FROM USER_TAB_COLUMNS C
                     INNER JOIN USER_TABLES T
                                ON T.TABLE_NAME = C.TABLE_NAME
                     LEFT JOIN USER_COL_COMMENTS D
                               ON D.TABLE_NAME = C.TABLE_NAME
                                   AND D.COLUMN_NAME = C.COLUMN_NAME
            WHERE %s
            ORDER BY C.TABLE_NAME,
                     C.COLUMN_ID
            SQL,
            $this->buildTableQueryPredicate('C', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield $this->createTableColumn($row);
        }
    }

    /**
     * @param list<mixed> $row
     *
     * @throws TypesException
     */
    private function createTableColumn(array $row): TableColumnMetadataRow
    {
        [
            $tableName,
            $columnName,
            $dataType,
            $dataDefault,
            $dataPrecision,
            $dataScale,
            $characterLength,
            $dataLength,
            $nullable,
            $comments,
        ] = $row;

        $dbType = strtolower($dataType);
        if (str_starts_with($dbType, 'timestamp(')) {
            if (str_contains($dbType, 'with time zone')) {
                $dbType = 'timestamptz';
            } else {
                $dbType = 'timestamp';
            }
        }

        $editor = Column::editor()
            ->setQuotedName($columnName);

        $precision = null;
        $scale     = 0;

        if ($dataPrecision !== null) {
            $precision = (int) $dataPrecision;
        }

        if ($dataScale !== null) {
            $scale = (int) $dataScale;
        }

        $type = $this->platform->getDoctrineTypeMapping($dbType);

        switch ($dbType) {
            case 'number':
                if ($precision === 20 && $scale === 0) {
                    $type = 'bigint';
                } elseif ($precision === 5 && $scale === 0) {
                    $type = 'smallint';
                } elseif ($precision === 1 && $scale === 0) {
                    $type = 'boolean';
                } elseif ($scale > 0) {
                    $type = 'decimal';
                }

                break;

            case 'float':
                if ($precision === 63) {
                    $type = 'smallfloat';
                }

                break;

            case 'varchar':
            case 'varchar2':
            case 'nvarchar2':
                $editor->setLength((int) $characterLength);
                break;

            case 'raw':
                $editor
                    ->setLength((int) $dataLength)
                    ->setFixed(true);
                break;

            case 'char':
            case 'nchar':
                $editor
                    ->setLength((int) $characterLength)
                    ->setFixed(true);
                break;
        }

        $editor
            ->setTypeName($type)
            ->setPrecision($precision)
            ->setScale($scale)
            ->setNotNull($nullable === 'N')
            ->setDefaultValue($this->parseDefaultExpression($dataDefault));

        if ($comments !== null) {
            $editor->setComment($comments);
        }

        return new TableColumnMetadataRow(null, $tableName, $editor->create());
    }

    private function parseDefaultExpression(?string $expression): ?string
    {
        // Default values returned from the database sometimes have trailing spaces.
        if (is_string($expression)) {
            $expression = trim($expression);
        }

        if ($expression === null || $expression === 'NULL') {
            return null;
        }

        // Default values returned from the database are represented as literal expressions
        if (preg_match('/^\'(.*)\'$/s', $expression, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        return $expression;
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForAllTables(): iterable
    {
        return $this->getIndexColumns(null);
    }

    /** {@inheritDoc} */
    public function getIndexColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getIndexColumns($tableName);
    }

    /**
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_INDEXES.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_CONSTRAINTS.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_IND_COLUMNS.html
     *
     * @return iterable<IndexColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getIndexColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT I.TABLE_NAME,
                   I.INDEX_NAME,
                   I.UNIQUENESS,
                   IC.COLUMN_NAME
            FROM USER_INDEXES I
                     LEFT JOIN USER_CONSTRAINTS C
                               ON C.INDEX_NAME = I.INDEX_NAME
                     JOIN USER_IND_COLUMNS IC
                          ON IC.INDEX_NAME = I.INDEX_NAME
            WHERE %s
              AND (C.CONSTRAINT_TYPE IS NULL OR C.CONSTRAINT_TYPE != 'P')
            ORDER BY I.TABLE_NAME,
                     I.INDEX_NAME,
                     IC.COLUMN_POSITION
            SQL,
            $this->buildTableQueryPredicate('I', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new IndexColumnMetadataRow(
                schemaName: null,
                tableName: $row[0],
                indexName: $row[1],
                type: $row[2] === 'UNIQUE' ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: false,
                predicate: null,
                columnName: $row[3],
                columnLength: null,
            );
        }
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getPrimaryKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getPrimaryKeyConstraintColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getPrimaryKeyConstraintColumns($tableName);
    }

    /**
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_CONSTRAINTS.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_CONS_COLUMNS.html
     *
     * @return iterable<PrimaryKeyConstraintColumnRow>
     *
     * @throws Exception
     */
    private function getPrimaryKeyConstraintColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT C.TABLE_NAME,
                   C.CONSTRAINT_NAME,
                   CC.COLUMN_NAME
            FROM USER_CONSTRAINTS C
                     JOIN USER_CONS_COLUMNS CC
                          ON CC.TABLE_NAME = C.TABLE_NAME
                              AND CC.CONSTRAINT_NAME = C.CONSTRAINT_NAME
            WHERE %s
              AND C.CONSTRAINT_TYPE = 'P'
            ORDER BY C.TABLE_NAME,
                C.CONSTRAINT_NAME,
                CC.POSITION
            SQL,
            $this->buildTableQueryPredicate('C', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new PrimaryKeyConstraintColumnRow(
                schemaName: null,
                tableName: $row[0],
                constraintName: $row[1],
                isClustered: true,
                columnName: $row[2],
            );
        }
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getForeignKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForTable(
        ?string $schemaName,
        string $tableName,
    ): iterable {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getForeignKeyConstraintColumns($tableName);
    }

    /**
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_CONSTRAINTS.html
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_CONS_COLUMNS.html
     *
     * @return iterable<ForeignKeyConstraintColumnMetadataRow>
     *
     * @throws Exception
     */
    private function getForeignKeyConstraintColumns(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT C.TABLE_NAME,
                   C.CONSTRAINT_NAME,
                   FKC.TABLE_NAME,
                   C.DELETE_RULE,
                   C.DEFERRABLE,
                   C.DEFERRED,
                   PKC.COLUMN_NAME,
                   FKC.COLUMN_NAME
            FROM USER_CONSTRAINTS C
                     JOIN USER_CONS_COLUMNS PKC
                          ON PKC.CONSTRAINT_NAME = C.CONSTRAINT_NAME
                     JOIN USER_CONS_COLUMNS FKC
                          ON FKC.CONSTRAINT_NAME = C.R_CONSTRAINT_NAME
                              AND FKC.POSITION = PKC.POSITION
            WHERE %s
              AND C.CONSTRAINT_TYPE = 'R'
            ORDER BY PKC.TABLE_NAME,
                     PKC.CONSTRAINT_NAME,
                     PKC.POSITION
SQL,
            $this->buildTableQueryPredicate('C', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new ForeignKeyConstraintColumnMetadataRow(
                referencingSchemaName: null,
                referencingTableName: $row[0],
                id: null,
                name: $row[1],
                referencedSchemaName: null,
                referencedTableName: $row[2],
                matchType: MatchType::SIMPLE,
                onUpdateAction: ReferentialAction::NO_ACTION,
                onDeleteAction: $this->createReferentialAction($row[3]),
                isDeferrable: $row[4] === 'DEFERRABLE',
                isDeferred: $row[5] === 'DEFERRED',
                referencingColumnName: $row[6],
                referencedColumnName: $row[7],
            );
        }
    }

    private function createReferentialAction(string $value): ReferentialAction
    {
        $action = ReferentialAction::tryFrom($value);
        assert($action !== null);

        return $action;
    }

    /** {@inheritDoc} */
    public function getTableOptionsForAllTables(): iterable
    {
        return $this->getTableOptions(null);
    }

    /** {@inheritDoc} */
    public function getTableOptionsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getTableOptions($tableName);
    }

    /**
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_TAB_COMMENTS.html
     *
     * @return iterable<TableMetadataRow>
     *
     * @throws Exception
     */
    private function getTableOptions(?string $tableName): iterable
    {
        $params = [];

        $sql = sprintf(
            <<<'SQL'
            SELECT TABLE_NAME,
                   COMMENTS
            FROM ALL_TAB_COMMENTS
            WHERE %s
            ORDER BY TABLE_NAME
            SQL,
            $this->buildTableQueryPredicate(null, $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            yield new TableMetadataRow(null, $row[0], [
                'comment' => $row[1],
            ]);
        }
    }

    /**
     * @param ?non-empty-string     $relation
     * @param array<string, string> $params
     *
     * @return non-empty-string
     */
    private function buildTableQueryPredicate(?string $relation, ?string $tableName, array &$params): string
    {
        $conditions = [];

        if ($tableName !== null) {
            $qualifier    = $relation !== null ? $relation . '.' : '';
            $conditions[] = $qualifier . 'TABLE_NAME = :TABLE_NAME';

            $params['TABLE_NAME'] = $tableName;
        } else {
            $conditions[] = '1 = 1';
        }

        return implode(' AND ', $conditions);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_VIEWS.html
     */
    public function getAllViews(): iterable
    {
        $sql = <<<'SQL'
        SELECT VIEW_NAME,
               TEXT
        FROM USER_VIEWS
        ORDER BY VIEW_NAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            yield new ViewMetadataRow(null, ...$row);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/refrn/USER_SEQUENCES.html
     */
    public function getAllSequences(): iterable
    {
        $sql = <<<'SQL'
        SELECT SEQUENCE_NAME,
               INCREMENT_BY,
               MIN_VALUE,
               CACHE_SIZE
        FROM USER_SEQUENCES
        ORDER BY SEQUENCE_NAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            $cacheSize = (int) $row[3];
            assert($cacheSize > 0);

            yield new SequenceMetadataRow(
                schemaName: null,
                sequenceName: $row[0],
                allocationSize: (int) $row[1],
                initialValue: (int) $row[2],
                cacheSize: $cacheSize,
            );
        }
    }
}

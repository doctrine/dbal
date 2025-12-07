<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Db2;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\UnsupportedName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Metadata\ForeignKeyConstraintColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\IndexColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\MetadataProvider;
use Doctrine\DBAL\Schema\Metadata\PrimaryKeyConstraintColumnRow;
use Doctrine\DBAL\Schema\Metadata\TableColumnMetadataRow;
use Doctrine\DBAL\Schema\Metadata\TableMetadataRow;
use Doctrine\DBAL\Schema\Metadata\ViewMetadataRow;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Types;

use function implode;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;

/** @link https://www.ibm.com/docs/en/db2/12.1.0?topic=sql-catalog-views */
final readonly class Db2MetadataProvider implements MetadataProvider
{
    /** @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatreferences */
    private const REFERENTIAL_ACTIONS = [
        'A' => ReferentialAction::NO_ACTION,
        'C' => ReferentialAction::CASCADE,
        'N' => ReferentialAction::SET_NULL,
        'R' => ReferentialAction::RESTRICT,
    ];

    /** @internal This class can be instantiated only by a database platform. */
    public function __construct(private Connection $connection, private DB2Platform $platform)
    {
    }

    /** {@inheritDoc} */
    public function getAllDatabaseNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /** {@inheritDoc} */
    public function getAllSchemaNames(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     */
    public function getAllTableNames(): iterable
    {
        $sql = <<<'SQL'
        SELECT TABNAME
        FROM SYSCAT.TABLES
        WHERE TABSCHEMA = CURRENT USER
          AND TYPE = 'T'
        ORDER BY TABNAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            [$tableName] = $row;

            assert(is_string($tableName) && $tableName !== '');
            yield new TableMetadataRow(null, $tableName, []);
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatcolumns
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
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
            SELECT C.TABNAME,
                   C.COLNAME,
                   C.TYPENAME,
                   C.CODEPAGE,
                   C.NULLS,
                   C.LENGTH,
                   C.SCALE,
                   C.REMARKS,
                   C.GENERATED,
                   C.DEFAULT
            FROM SYSCAT.COLUMNS C
                     JOIN SYSCAT.TABLES AS T
                          ON T.TABSCHEMA = C.TABSCHEMA
                              AND T.TABNAME = C.TABNAME
            WHERE %s
              AND T.TYPE = 'T'
            ORDER BY C.TABNAME,
                     C.COLNO
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
            $typeName,
            $codePage,
            $nulls,
            $length,
            $scale,
            $remarks,
            $generated,
            $default,
        ] = $row;

        assert(is_string($tableName) && $tableName !== '');
        assert(is_string($columnName) && $columnName !== '');
        assert(is_string($typeName));
        assert(is_int($codePage));
        assert(is_string($nulls));
        assert($length === null || is_int($length));
        assert($scale === null || is_int($scale));
        assert($remarks === null || is_string($remarks));
        assert(is_string($generated));
        assert($default === null || is_string($default));

        $editor = Column::editor()
            ->setQuotedName($columnName);

        $type = $this->platform->getDoctrineTypeMapping($typeName);

        switch (strtolower($typeName)) {
            case 'varchar':
                if ($codePage === 0) {
                    $type = Types::BINARY;
                }

                $editor->setLength($length);
                break;

            case 'character':
                if ($codePage === 0) {
                    $type = Types::BINARY;
                }

                $editor
                    ->setLength($length)
                    ->setFixed(true);
                break;

            case 'clob':
                $editor->setLength($length);
                break;

            case 'decimal':
            case 'double':
            case 'real':
                $editor->setPrecision($length);
                if ($scale !== null) {
                    $editor->setScale($scale);
                }

                break;
        }

        $editor
            ->setTypeName($type)
            ->setNotNull($nulls === 'N')
            ->setDefaultValue($this->parseDefaultExpression($default))
            ->setAutoincrement($generated === 'D');

        if ($remarks !== null) {
            $editor->setComment($remarks);
        }

        return new TableColumnMetadataRow(null, $tableName, $editor->create());
    }

    private function parseDefaultExpression(?string $expression): ?string
    {
        if ($expression === null || $expression === 'NULL') {
            return null;
        }

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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatindexcoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatindexes
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
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
            SELECT I.TABNAME,
                   I.INDNAME,
                   I.UNIQUERULE,
                   ICU.COLNAME
            FROM SYSCAT.INDEXES AS I
                     JOIN SYSCAT.TABLES AS T
                          ON I.TABSCHEMA = T.TABSCHEMA
                                 AND I.TABNAME = T.TABNAME
                     JOIN SYSCAT.INDEXCOLUSE AS ICU
                          ON I.INDSCHEMA = ICU.INDSCHEMA
                                 AND I.INDNAME = ICU.INDNAME
            WHERE %s
              AND T.TYPE = 'T'
              AND I.UNIQUERULE != 'P'
            ORDER BY I.TABNAME,
                I.INDNAME,
                ICU.COLSEQ
            SQL,
            $this->buildTableQueryPredicate('I', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [$tableName, $indexName, $uniqueRule, $columnName] = $row;

            assert(is_string($tableName) && $tableName !== '');
            assert(is_string($indexName) && $indexName !== '');
            assert(is_string($uniqueRule));
            assert(is_string($columnName) && $columnName !== '');
            yield new IndexColumnMetadataRow(
                schemaName: null,
                tableName: $tableName,
                indexName: $indexName,
                type: $uniqueRule === 'U' ? IndexType::UNIQUE : IndexType::REGULAR,
                isClustered: false,
                predicate: null,
                columnName: $columnName,
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatcoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattabconst
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
            SELECT TC.TABNAME,
                   TC.CONSTNAME,
                   KCU.COLNAME
            FROM SYSCAT.TABCONST TC
                     JOIN SYSCAT.KEYCOLUSE KCU
                          ON KCU.TABSCHEMA = TC.TABSCHEMA
                              AND KCU.TABNAME = TC.TABNAME
                              AND KCU.CONSTNAME = TC.CONSTNAME
            WHERE %s
              AND TC.TYPE = 'P'
            ORDER BY TC.TABNAME,
                TC.CONSTNAME,
                KCU.COLSEQ
            SQL,
            $this->buildTableQueryPredicate('TC', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [
                $tableName,
                $constraintName,
                $columnName,
            ] = $row;

            assert(is_string($tableName) && $tableName !== '');
            assert($constraintName === null || (is_string($constraintName) && $constraintName !== ''));
            assert(is_string($columnName) && $columnName !== '');

            yield new PrimaryKeyConstraintColumnRow(
                schemaName: null,
                tableName: $tableName,
                constraintName: $constraintName,
                isClustered: true,
                columnName: $columnName,
            );
        }
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForAllTables(): iterable
    {
        return $this->getForeignKeyConstraintColumns(null);
    }

    /** {@inheritDoc} */
    public function getForeignKeyConstraintColumnsForTable(?string $schemaName, string $tableName): iterable
    {
        if ($schemaName !== null) {
            throw UnsupportedName::fromNonNullSchemaName($schemaName, __METHOD__);
        }

        return $this->getForeignKeyConstraintColumns($tableName);
    }

    /**
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatkeycoluse
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatreferences
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
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
            SELECT R.TABNAME,
                   R.CONSTNAME,
                   R.REFTABNAME,
                   R.UPDATERULE,
                   R.DELETERULE,
                   PKCU.COLNAME,
                   FKCU.COLNAME
            FROM SYSCAT.REFERENCES AS R
                     JOIN SYSCAT.TABLES AS T
                          ON T.TABSCHEMA = R.TABSCHEMA
                              AND T.TABNAME = R.TABNAME
                     JOIN SYSCAT.KEYCOLUSE AS PKCU
                          ON PKCU.CONSTNAME = R.CONSTNAME
                              AND PKCU.TABSCHEMA = R.TABSCHEMA
                              AND PKCU.TABNAME = R.TABNAME
                     JOIN SYSCAT.KEYCOLUSE AS FKCU
                          ON FKCU.CONSTNAME = R.REFKEYNAME
                              AND FKCU.TABSCHEMA = R.REFTABSCHEMA
                              AND FKCU.TABNAME = R.REFTABNAME
                              AND FKCU.COLSEQ = PKCU.COLSEQ
            WHERE %s
              AND T.TYPE = 'T'
            ORDER BY R.TABNAME,
                     R.CONSTNAME,
                     PKCU.COLSEQ
            SQL,
            $this->buildTableQueryPredicate('R', $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [
                $referencingTableName,
                $name,
                $referencedTableName,
                $updateRule,
                $deleteRule,
                $referencingColumnName,
                $referencedColumnName,
            ] = $row;

            assert(is_string($referencingTableName) && $referencingTableName !== '');
            assert($name === null || (is_string($name) && $name !== ''));
            assert(is_string($referencedTableName) && $referencedTableName !== '');
            assert(is_string($updateRule) && isset(self::REFERENTIAL_ACTIONS[$updateRule]));
            assert(is_string($deleteRule) && isset(self::REFERENTIAL_ACTIONS[$deleteRule]));
            assert(is_string($referencingColumnName) && $referencingColumnName !== '');
            assert(is_string($referencedColumnName) && $referencedColumnName !== '');

            yield new ForeignKeyConstraintColumnMetadataRow(
                referencingSchemaName: null,
                referencingTableName: $referencingTableName,
                id: null,
                name: $name,
                referencedSchemaName: null,
                referencedTableName: $referencedTableName,
                matchType: MatchType::SIMPLE,
                onUpdateAction: self::REFERENTIAL_ACTIONS[$updateRule],
                onDeleteAction: self::REFERENTIAL_ACTIONS[$deleteRule],
                isDeferrable: false,
                isDeferred: false,
                referencingColumnName: $referencingColumnName,
                referencedColumnName: $referencedColumnName,
            );
        }
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
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscattables
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
            SELECT TABNAME,
                   REMARKS
            FROM SYSCAT.TABLES T
            WHERE %s
              AND TYPE = 'T'
            ORDER BY TABNAME
            SQL,
            $this->buildTableQueryPredicate(null, $tableName, $params),
        );

        foreach ($this->connection->iterateNumeric($sql, $params) as $row) {
            [$tableName, $comment] = $row;
            assert(is_string($tableName) && $tableName !== '');
            assert($comment === null || is_string($comment));
            yield new TableMetadataRow(null, $tableName, [
                'comment' => $comment,
            ]);
        }
    }

    /**
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     *
     * @param ?non-empty-string $relation
     * @param list<string>      $params
     *
     * @return non-empty-string
     */
    private function buildTableQueryPredicate(?string $relation, ?string $tableName, array &$params): string
    {
        $qualifier = $relation !== null ? $relation . '.' : '';

        $conditions = [$qualifier . 'TABSCHEMA = CURRENT USER'];

        if ($tableName !== null) {
            $conditions[] = $qualifier . 'TABNAME = ?';
            $params[]     = $tableName;
        }

        return implode(' AND ', $conditions);
    }

    /**
     * {@inheritDoc}
     *
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=views-syscatviews
     * @link https://www.ibm.com/docs/en/db2/12.1.0?topic=registers-current-user
     */
    public function getAllViews(): iterable
    {
        $sql = <<<'SQL'
        SELECT VIEWNAME,
               TEXT
        FROM SYSCAT.VIEWS
        WHERE VIEWSCHEMA = CURRENT USER
        ORDER BY VIEWNAME
        SQL;

        foreach ($this->connection->iterateNumeric($sql) as $row) {
            [$viewName, $definition] = $row;
            assert(is_string($viewName) && $viewName !== '');
            assert(is_string($definition));
            yield new ViewMetadataRow(null, $viewName, $definition);
        }
    }

    /** {@inheritDoc} */
    public function getAllSequences(): iterable
    {
        throw NotSupported::new(__METHOD__);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_key_exists;
use function array_map;
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

use const CASE_LOWER;

/**
 * Oracle Schema Manager.
 *
 * @extends AbstractSchemaManager<OraclePlatform>
 */
class OracleSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritDoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        $view = array_change_key_case($view, CASE_LOWER);

        return new View($this->getQuotedIdentifierName($view['view_name']), $view['text']);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableIndexesList(array $rows): array
    {
        return parent::_getPortableTableIndexesList(array_map(
            /** @param array<string, mixed> $row */
            static function (array $row): array {
                $row = array_change_key_case($row);

                return [
                    'key_name' => $row['index_name'],
                    'type' => $row['uniqueness'] === 'UNIQUE' ? IndexType::UNIQUE : IndexType::REGULAR,
                    'column_name' => $row['column_name'],
                ];
            },
            $rows,
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['data_type']);
        if (str_starts_with($dbType, 'timestamp(')) {
            if (str_contains($dbType, 'with time zone')) {
                $dbType = 'timestamptz';
            } else {
                $dbType = 'timestamp';
            }
        }

        $length = $precision = null;
        $scale  = 0;
        $fixed  = false;

        assert(array_key_exists('data_default', $tableColumn));

        // Default values returned from database sometimes have trailing spaces.
        if (is_string($tableColumn['data_default'])) {
            $tableColumn['data_default'] = trim($tableColumn['data_default']);
        }

        if ($tableColumn['data_default'] === '' || $tableColumn['data_default'] === 'NULL') {
            $tableColumn['data_default'] = null;
        }

        if ($tableColumn['data_default'] !== null) {
            // Default values returned from database are represented as literal expressions
            if (preg_match('/^\'(.*)\'$/s', $tableColumn['data_default'], $matches) === 1) {
                $tableColumn['data_default'] = str_replace("''", "'", $matches[1]);
            }
        }

        if ($tableColumn['data_precision'] !== null) {
            $precision = (int) $tableColumn['data_precision'];
        }

        if ($tableColumn['data_scale'] !== null) {
            $scale = (int) $tableColumn['data_scale'];
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
                $length = (int) $tableColumn['char_length'];
                break;

            case 'raw':
                $length = (int) $tableColumn['data_length'];
                $fixed  = true;
                break;

            case 'char':
            case 'nchar':
                $length = (int) $tableColumn['char_length'];
                $fixed  = true;
                break;
        }

        $options = [
            'notnull'    => $tableColumn['nullable'] === 'N',
            'fixed'      => $fixed,
            'default'    => $tableColumn['data_default'],
            'length'     => $length,
            'precision'  => $precision,
            'scale'      => $scale,
        ];

        if ($tableColumn['comments'] !== null) {
            $options['comment'] = $tableColumn['comments'];
        }

        return new Column($this->getQuotedIdentifierName($tableColumn['column_name']), Type::getType($type), $options);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableTableForeignKeysList(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (! isset($list[$row['constraint_name']])) {
                if ($row['delete_rule'] === 'NO ACTION') {
                    $row['delete_rule'] = null;
                }

                $list[$row['constraint_name']] = [
                    'name' => $row['constraint_name'],
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $row['references_table'],
                    'onDelete' => $row['delete_rule'],
                    'deferrable' => $row['deferrable'] === 'DEFERRABLE',
                    'deferred' => $row['deferred'] === 'DEFERRED',
                ];
            }

            $localColumn   = $row['local_column'];
            $foreignColumn = $row['foreign_column'];

            $list[$row['constraint_name']]['local'][]   = $localColumn;
            $list[$row['constraint_name']]['foreign'][] = $foreignColumn;
        }

        return parent::_getPortableTableForeignKeysList($list);
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        $sequence = array_change_key_case($sequence, CASE_LOWER);

        return new Sequence(
            $this->getQuotedIdentifierName($sequence['sequence_name']),
            (int) $sequence['increment_by'],
            (int) $sequence['min_value'],
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        $database = array_change_key_case($database, CASE_LOWER);

        return $database['username'];
    }

    public function createDatabase(string $database): void
    {
        $statement = $this->platform->getCreateDatabaseSQL($database);

        $params = $this->connection->getParams();

        if (isset($params['password'])) {
            $statement .= ' IDENTIFIED BY ' . $params['password'];
        }

        $this->connection->executeStatement($statement);

        $statement = 'GRANT DBA TO ' . $database;
        $this->connection->executeStatement($statement);
    }

    /** @throws Exception */
    private function dropAutoincrement(OptionallyQualifiedName $table): void
    {
        $sql = $this->platform->getDropAutoincrementSql($table);
        foreach ($sql as $query) {
            $this->connection->executeStatement($query);
        }
    }

    public function dropTable(string $name): void
    {
        $parser = Parsers::getOptionallyQualifiedNameParser();

        try {
            $tableName = $parser->parse($name);
        } catch (Parser\Exception $e) {
            throw InvalidName::fromParserException($name, $e);
        }

        try {
            $this->dropAutoincrement($tableName);
        } catch (DatabaseObjectNotFoundException) {
        }

        parent::dropTable($name);
    }

    /**
     * Returns the quoted representation of the given identifier name.
     *
     * Quotes non-uppercase identifiers explicitly to preserve case
     * and thus make references to the particular identifier work.
     */
    private function getQuotedIdentifierName(string $identifier): string
    {
        if (preg_match('/[a-z]/', $identifier) === 1) {
            return $this->platform->quoteSingleIdentifier($identifier);
        }

        return $identifier;
    }

    protected function selectTableNames(string $databaseName): Result
    {
        $sql = sprintf(
            <<<'SQL'
                SELECT TABLE_NAME AS %s
                FROM ALL_TABLES
                WHERE OWNER = :OWNER
                ORDER BY TABLE_NAME
                SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
        );

        return $this->connection->executeQuery($sql, ['OWNER' => $databaseName]);
    }

    protected function selectTableColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['C.OWNER = :OWNER'];
        $params     = ['OWNER' => $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[]         = 'C.TABLE_NAME = :TABLE_NAME';
            $params['TABLE_NAME'] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
          SELECT
                 C.TABLE_NAME AS %s,
                 C.COLUMN_NAME,
                 C.DATA_TYPE,
                 C.DATA_DEFAULT,
                 C.DATA_PRECISION,
                 C.DATA_SCALE,
                 C.CHAR_LENGTH,
                 C.DATA_LENGTH,
                 C.NULLABLE,
                 D.COMMENTS
            FROM ALL_TAB_COLUMNS C
        INNER JOIN ALL_TABLES T
            ON T.OWNER = C.OWNER
            AND T.TABLE_NAME = C.TABLE_NAME
       LEFT JOIN ALL_COL_COMMENTS D
           ON D.OWNER = C.OWNER
                  AND D.TABLE_NAME = C.TABLE_NAME
                  AND D.COLUMN_NAME = C.COLUMN_NAME
           WHERE %s
        ORDER BY C.TABLE_NAME, C.COLUMN_ID
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    protected function selectIndexColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ['IND_COL.INDEX_OWNER = :OWNER'];
        $params     = ['OWNER' => $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[]         = 'IND_COL.TABLE_NAME = :TABLE_NAME';
            $params['TABLE_NAME'] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
          SELECT
                 IND_COL.TABLE_NAME AS %s,
                 IND_COL.INDEX_NAME,
                 IND.UNIQUENESS,
                 IND_COL.COLUMN_NAME,
                 CON.CONSTRAINT_TYPE
            FROM ALL_IND_COLUMNS IND_COL
       LEFT JOIN ALL_INDEXES IND
              ON IND.OWNER = IND_COL.INDEX_OWNER
             AND IND.INDEX_NAME = IND_COL.INDEX_NAME
       LEFT JOIN ALL_CONSTRAINTS CON
              ON CON.OWNER = IND_COL.INDEX_OWNER
             AND CON.INDEX_NAME = IND_COL.INDEX_NAME
           WHERE %s
             AND (CON.CONSTRAINT_TYPE IS NULL OR CON.CONSTRAINT_TYPE != 'P')
        ORDER BY IND_COL.TABLE_NAME,
                 IND_COL.INDEX_NAME,
                 IND_COL.COLUMN_POSITION
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchPrimaryKeyConstraintColumns(
        string $databaseName,
        ?OptionallyQualifiedName $tableName = null,
    ): array {
        $conditions = ['AC.OWNER = :OWNER'];
        $params     = ['OWNER' => $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[]         = 'AC.TABLE_NAME = :TABLE_NAME';
            $params['TABLE_NAME'] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
            SELECT
                AC.TABLE_NAME,
                AC.CONSTRAINT_NAME,
                ACC.COLUMN_NAME
            FROM
                ALL_CONSTRAINTS AC
            INNER JOIN
                ALL_CONS_COLUMNS ACC
                ON ACC.OWNER = AC.OWNER
               AND ACC.TABLE_NAME = AC.TABLE_NAME
               AND ACC.CONSTRAINT_NAME = AC.CONSTRAINT_NAME
            WHERE %s
              AND AC.CONSTRAINT_TYPE = 'P'
            ORDER BY TABLE_NAME,
                     ACC.POSITION
SQL,
            implode(' AND ', $conditions),
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    protected function selectForeignKeyColumns(string $databaseName, ?OptionallyQualifiedName $tableName = null): Result
    {
        $conditions = ["ALC.CONSTRAINT_TYPE = 'R'", 'COLS.OWNER = :OWNER'];
        $params     = ['OWNER' => $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[]         = 'COLS.TABLE_NAME = :TABLE_NAME';
            $params['TABLE_NAME'] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
          SELECT
                 COLS.TABLE_NAME AS %s,
                 ALC.CONSTRAINT_NAME,
                 ALC.DELETE_RULE,
                 ALC.DEFERRABLE,
                 ALC.DEFERRED,
                 COLS.COLUMN_NAME LOCAL_COLUMN,
                 R_COLS.TABLE_NAME REFERENCES_TABLE,
                 R_COLS.COLUMN_NAME FOREIGN_COLUMN
            FROM ALL_CONS_COLUMNS COLS
       LEFT JOIN ALL_CONSTRAINTS ALC ON ALC.OWNER = COLS.OWNER AND ALC.CONSTRAINT_NAME = COLS.CONSTRAINT_NAME
       LEFT JOIN ALL_CONS_COLUMNS R_COLS ON R_COLS.OWNER = ALC.R_OWNER AND
                 R_COLS.CONSTRAINT_NAME = ALC.R_CONSTRAINT_NAME AND
                 R_COLS.POSITION = COLS.POSITION
           WHERE %s
        ORDER BY COLS.TABLE_NAME,
                 COLS.CONSTRAINT_NAME,
                 COLS.POSITION
SQL,
            $this->platform->quoteSingleIdentifier(self::TABLE_NAME_COLUMN),
            implode(' AND ', $conditions),
        );

        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchTableOptionsByTable(string $databaseName, ?OptionallyQualifiedName $tableName = null): array
    {
        $conditions = ['OWNER = :OWNER'];
        $params     = ['OWNER' => $databaseName];

        if ($tableName !== null) {
            $this->ensureUnqualifiedName($tableName, __METHOD__);

            $conditions[]         = 'TABLE_NAME = :TABLE_NAME';
            $params['TABLE_NAME'] = $tableName->getUnqualifiedName()->toNormalizedValue(
                $this->platform->getUnquotedIdentifierFolding(),
            );
        }

        $sql = sprintf(
            <<<'SQL'
      SELECT TABLE_NAME,
             COMMENTS
        FROM ALL_TAB_COMMENTS
      WHERE %s
   ORDER BY TABLE_NAME
SQL,
            implode(' AND ', $conditions),
        );

        $tableOptions = [];
        foreach ($this->connection->iterateKeyValue($sql, $params) as $table => $comments) {
            $tableOptions[self::NULL_SCHEMA_KEY][$table] = ['comment' => $comments];
        }

        return $tableOptions;
    }
}

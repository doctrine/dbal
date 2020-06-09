<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Types\Type;
use PDOException;
use Throwable;

use function assert;
use function count;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtok;

/**
 * SQL Server Schema Manager.
 */
class SQLServerSchemaManager extends AbstractSchemaManager
{
    public function dropDatabase(string $database): void
    {
        try {
            parent::dropDatabase($database);
        } catch (DBALException $exception) {
            $exception = $exception->getPrevious();
            assert($exception instanceof Throwable);

            if (! $exception instanceof DriverException) {
                throw $exception;
            }

            // If we have a error code 3702, the drop database operation failed
            // because of active connections on the database.
            // To force dropping the database, we first have to close all active connections
            // on that database and issue the drop database operation again.
            if ($exception->getCode() !== 3702) {
                throw $exception;
            }

            $this->closeActiveDatabaseConnections($database);

            parent::dropDatabase($database);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        return new Sequence($sequence['name'], (int) $sequence['increment'], (int) $sequence['start_value']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $dbType = strtok($tableColumn['type'], '(), ');
        assert(is_string($dbType));

        $length = (int) $tableColumn['length'];

        $precision = $default = null;

        $scale = 0;
        $fixed = false;

        if (! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        if ($tableColumn['scale'] !== null) {
            $scale = (int) $tableColumn['scale'];
        }

        if ($tableColumn['precision'] !== null) {
            $precision = (int) $tableColumn['precision'];
        }

        if ($tableColumn['default'] !== null) {
            $default = $this->parseDefaultExpression($tableColumn['default']);
        }

        switch ($dbType) {
            case 'nchar':
            case 'nvarchar':
            case 'ntext':
                // Unicode data requires 2 bytes per character
                $length /= 2;
                break;

            case 'varchar':
                // TEXT type is returned as VARCHAR(MAX) with a length of -1
                if ($length === -1) {
                    $dbType = 'text';
                }

                break;
        }

        if ($dbType === 'char' || $dbType === 'nchar' || $dbType === 'binary') {
            $fixed = true;
        }

        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'])
            ?? $this->_platform->getDoctrineTypeMapping($dbType);

        $options = [
            'fixed'         => $fixed,
            'default'       => $default,
            'notnull'       => (bool) $tableColumn['notnull'],
            'scale'         => $scale,
            'precision'     => $precision,
            'autoincrement' => (bool) $tableColumn['autoincrement'],
        ];

        if (isset($tableColumn['comment'])) {
            $options['comment'] = $tableColumn['comment'];
        }

        if ($length !== 0 && ($type === 'text' || $type === 'string')) {
            $options['length'] = $length;
        }

        $column = new Column($tableColumn['name'], Type::getType($type), $options);

        if (isset($tableColumn['collation']) && $tableColumn['collation'] !== 'NULL') {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }

    private function parseDefaultExpression(string $value): ?string
    {
        while (preg_match('/^\((.*)\)$/s', $value, $matches)) {
            $value = $matches[1];
        }

        if ($value === 'NULL') {
            return null;
        }

        if (preg_match('/^\'(.*)\'$/s', $value, $matches) === 1) {
            $value = str_replace("''", "'", $matches[1]);
        }

        if ($value === 'getdate()') {
            return $this->_platform->getCurrentTimestampSQL();
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $foreignKeys = [];

        foreach ($tableForeignKeys as $tableForeignKey) {
            if (! isset($foreignKeys[$tableForeignKey['ForeignKey']])) {
                $foreignKeys[$tableForeignKey['ForeignKey']] = [
                    'local_columns' => [$tableForeignKey['ColumnName']],
                    'foreign_table' => $tableForeignKey['ReferenceTableName'],
                    'foreign_columns' => [$tableForeignKey['ReferenceColumnName']],
                    'name' => $tableForeignKey['ForeignKey'],
                    'options' => [
                        'onUpdate' => str_replace('_', ' ', $tableForeignKey['update_referential_action_desc']),
                        'onDelete' => str_replace('_', ' ', $tableForeignKey['delete_referential_action_desc']),
                    ],
                ];
            } else {
                $foreignKeys[$tableForeignKey['ForeignKey']]['local_columns'][]   = $tableForeignKey['ColumnName'];
                $foreignKeys[$tableForeignKey['ForeignKey']]['foreign_columns'][] = $tableForeignKey['ReferenceColumnName'];
            }
        }

        return parent::_getPortableTableForeignKeysList($foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList(array $tableIndexRows, string $tableName): array
    {
        foreach ($tableIndexRows as &$tableIndex) {
            $tableIndex['non_unique'] = (bool) $tableIndex['non_unique'];
            $tableIndex['primary']    = (bool) $tableIndex['primary'];
            $tableIndex['flags']      = $tableIndex['flags'] ? [$tableIndex['flags']] : null;
        }

        return parent::_getPortableTableIndexesList($tableIndexRows, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition(array $tableForeignKey): ForeignKeyConstraint
    {
        return new ForeignKeyConstraint(
            $tableForeignKey['local_columns'],
            $tableForeignKey['foreign_table'],
            $tableForeignKey['foreign_columns'],
            $tableForeignKey['name'],
            $tableForeignKey['options']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        if (isset($table['schema_name']) && $table['schema_name'] !== 'dbo') {
            return $table['schema_name'] . '.' . $table['name'];
        }

        return $table['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        return $database['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getPortableNamespaceDefinition(array $namespace): string
    {
        return $namespace['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        // @todo
        return new View($view['name'], '');
    }

    /**
     * {@inheritdoc}
     */
    public function listTableIndexes(string $table): array
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        try {
            $tableIndexes = $this->_conn->fetchAllAssociative($sql);
        } catch (PDOException $e) {
            if ($e->getCode() === 'IMSSP') {
                return [];
            }

            throw $e;
        } catch (DBALException $e) {
            if (strpos($e->getMessage(), 'SQLSTATE [01000, 15472]') === 0) {
                return [];
            }

            throw $e;
        }

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    public function alterTable(TableDiff $tableDiff): void
    {
        if (count($tableDiff->removedColumns) > 0) {
            foreach ($tableDiff->removedColumns as $col) {
                $columnConstraintSql = $this->getColumnConstraintSQL($tableDiff->name, $col->getName());
                foreach ($this->_conn->fetchAllAssociative($columnConstraintSql) as $constraint) {
                    $this->_conn->exec(
                        sprintf(
                            'ALTER TABLE %s DROP CONSTRAINT %s',
                            $tableDiff->name,
                            $constraint['Name']
                        )
                    );
                }
            }
        }

        parent::alterTable($tableDiff);
    }

    /**
     * Returns the SQL to retrieve the constraints for a given column.
     */
    private function getColumnConstraintSQL(string $table, string $column): string
    {
        return "SELECT SysObjects.[Name]
            FROM SysObjects INNER JOIN (SELECT [Name],[ID] FROM SysObjects WHERE XType = 'U') AS Tab
            ON Tab.[ID] = Sysobjects.[Parent_Obj]
            INNER JOIN sys.default_constraints DefCons ON DefCons.[object_id] = Sysobjects.[ID]
            INNER JOIN SysColumns Col ON Col.[ColID] = DefCons.[parent_column_id] AND Col.[ID] = Tab.[ID]
            WHERE Col.[Name] = " . $this->_conn->quote($column) . ' AND Tab.[Name] = ' . $this->_conn->quote($table) . '
            ORDER BY Col.[Name]';
    }

    /**
     * Closes currently active connections on the given database.
     *
     * This is useful to force DROP DATABASE operations which could fail because of active connections.
     */
    private function closeActiveDatabaseConnections(string $database): void
    {
        $database = new Identifier($database);

        $this->_execSql(
            sprintf(
                'ALTER DATABASE %s SET SINGLE_USER WITH ROLLBACK IMMEDIATE',
                $database->getQuotedName($this->_platform)
            )
        );
    }

    public function listTableDetails(string $tableName): Table
    {
        $table = parent::listTableDetails($tableName);

        $platform = $this->_platform;
        assert($platform instanceof SQLServer2012Platform);
        $sql = $platform->getListTableMetadataSQL($tableName);

        $tableOptions = $this->_conn->fetchAssociative($sql);

        if ($tableOptions !== false) {
            $table->addOption('comment', $tableOptions['table_comment']);
        }

        return $table;
    }
}

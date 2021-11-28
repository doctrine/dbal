<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLServer;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

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
 *
 * @extends AbstractSchemaManager<SQLServerPlatform>
 */
class SQLServerSchemaManager extends AbstractSchemaManager
{
    /** @var string|null */
    private $databaseCollation;

    /**
     * {@inheritDoc}
     */
    public function listSchemaNames(): array
    {
        return $this->_conn->fetchFirstColumn(
            <<<'SQL'
SELECT name
FROM   sys.schemas
WHERE  name NOT IN('guest', 'INFORMATION_SCHEMA', 'sys')
SQL
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequenceDefinition($sequence)
    {
        return new Sequence($sequence['name'], (int) $sequence['increment'], (int) $sequence['start_value']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtok($tableColumn['type'], '(), ');
        assert(is_string($dbType));

        $fixed   = null;
        $length  = (int) $tableColumn['length'];
        $default = $tableColumn['default'];

        if (! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        if ($default !== null) {
            $default = $this->parseDefaultExpression($default);
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

            case 'varbinary':
                if ($length === -1) {
                    $dbType = 'blob';
                }

                break;
        }

        if ($dbType === 'char' || $dbType === 'nchar' || $dbType === 'binary') {
            $fixed = true;
        }

        $type                   = $this->_platform->getDoctrineTypeMapping($dbType);
        $type                   = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        $options = [
            'unsigned'      => false,
            'fixed'         => (bool) $fixed,
            'default'       => $default,
            'notnull'       => (bool) $tableColumn['notnull'],
            'scale'         => $tableColumn['scale'],
            'precision'     => $tableColumn['precision'],
            'autoincrement' => (bool) $tableColumn['autoincrement'],
            'comment'       => $tableColumn['comment'] !== '' ? $tableColumn['comment'] : null,
        ];

        if ($length !== 0 && ($type === 'text' || $type === 'string' || $type === 'binary')) {
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
    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $foreignKeys = [];

        foreach ($tableForeignKeys as $tableForeignKey) {
            $name = $tableForeignKey['ForeignKey'];

            if (! isset($foreignKeys[$name])) {
                $foreignKeys[$name] = [
                    'local_columns' => [$tableForeignKey['ColumnName']],
                    'foreign_table' => $tableForeignKey['ReferenceTableName'],
                    'foreign_columns' => [$tableForeignKey['ReferenceColumnName']],
                    'name' => $name,
                    'options' => [
                        'onUpdate' => str_replace('_', ' ', $tableForeignKey['update_referential_action_desc']),
                        'onDelete' => str_replace('_', ' ', $tableForeignKey['delete_referential_action_desc']),
                    ],
                ];
            } else {
                $foreignKeys[$name]['local_columns'][]   = $tableForeignKey['ColumnName'];
                $foreignKeys[$name]['foreign_columns'][] = $tableForeignKey['ReferenceColumnName'];
            }
        }

        return parent::_getPortableTableForeignKeysList($foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        foreach ($tableIndexes as &$tableIndex) {
            $tableIndex['non_unique'] = (bool) $tableIndex['non_unique'];
            $tableIndex['primary']    = (bool) $tableIndex['primary'];
            $tableIndex['flags']      = $tableIndex['flags'] ? [$tableIndex['flags']] : null;
        }

        return parent::_getPortableTableIndexesList($tableIndexes, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
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
    protected function _getPortableTableDefinition($table)
    {
        if (isset($table['schema_name']) && $table['schema_name'] !== 'dbo') {
            return $table['schema_name'] . '.' . $table['name'];
        }

        return $table['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['name'];
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use {@link listSchemaNames()} instead.
     */
    protected function getPortableNamespaceDefinition(array $namespace)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/4503',
            'SQLServerSchemaManager::getPortableNamespaceDefinition() is deprecated,'
                . ' use SQLServerSchemaManager::listSchemaNames() instead.'
        );

        return $namespace['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition($view)
    {
        // @todo
        return new View($view['name'], $view['definition']);
    }

    /**
     * {@inheritdoc}
     */
    public function listTableIndexes($table)
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        try {
            $tableIndexes = $this->_conn->fetchAllAssociative($sql);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'SQLSTATE [01000, 15472]') === 0) {
                return [];
            }

            throw $e;
        }

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * {@inheritdoc}
     */
    public function alterTable(TableDiff $tableDiff)
    {
        if (count($tableDiff->removedColumns) > 0) {
            foreach ($tableDiff->removedColumns as $col) {
                foreach ($this->getColumnConstraints($tableDiff->name, $col->getName()) as $constraint) {
                    $this->_conn->executeStatement(
                        sprintf(
                            'ALTER TABLE %s DROP CONSTRAINT %s',
                            $tableDiff->name,
                            $constraint
                        )
                    );
                }
            }
        }

        parent::alterTable($tableDiff);
    }

    /**
     * Returns the names of the constraints for a given column.
     *
     * @return iterable<string>
     *
     * @throws Exception
     */
    private function getColumnConstraints(string $table, string $column): iterable
    {
        return $this->_conn->iterateColumn(
            <<<'SQL'
SELECT o.name
FROM sys.objects o
         INNER JOIN sys.objects t
                    ON t.object_id = o.parent_object_id
                        AND t.type = 'U'
         INNER JOIN sys.default_constraints dc
                    ON dc.object_id = o.object_id
         INNER JOIN sys.columns c
                    ON c.column_id = dc.parent_column_id
                        AND c.object_id = t.object_id
WHERE t.name = ?
  AND c.name = ?
SQL
            ,
            [$table, $column]
        );
    }

    /**
     * @param string $name
     *
     * @throws Exception
     */
    public function listTableDetails($name): Table
    {
        $table = parent::listTableDetails($name);

        $sql = $this->_platform->getListTableMetadataSQL($name);

        $tableOptions = $this->_conn->fetchAssociative($sql);

        if ($tableOptions !== false) {
            $table->addOption('comment', $tableOptions['table_comment']);
        }

        return $table;
    }

    /**
     * @throws Exception
     */
    public function createComparator(): Comparator
    {
        return new SQLServer\Comparator($this->getDatabasePlatform(), $this->getDatabaseCollation());
    }

    /**
     * @throws Exception
     */
    private function getDatabaseCollation(): string
    {
        if ($this->databaseCollation === null) {
            $databaseCollation = $this->_conn->fetchOne(
                'SELECT collation_name FROM sys.databases WHERE name = '
                . $this->_platform->getCurrentDatabaseExpression(),
            );

            // a database is always selected, even if omitted in the connection parameters
            assert(is_string($databaseCollation));

            $this->databaseCollation = $databaseCollation;
        }

        return $this->databaseCollation;
    }
}

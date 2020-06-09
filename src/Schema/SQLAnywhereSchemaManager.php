<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Types\Type;

use function assert;
use function is_string;
use function preg_replace;

/**
 * SAP Sybase SQL Anywhere schema manager.
 */
class SQLAnywhereSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     *
     * Starts a database after creation
     * as SQL Anywhere needs a database to be started
     * before it can be used.
     *
     * @see startDatabase
     */
    public function createDatabase(string $database): void
    {
        parent::createDatabase($database);
        $this->startDatabase($database);
    }

    /**
     * {@inheritdoc}
     *
     * Tries stopping a database before dropping
     * as SQL Anywhere needs a database to be stopped
     * before it can be dropped.
     *
     * @see stopDatabase
     */
    public function dropDatabase(string $database): void
    {
        $this->tryMethod('stopDatabase', $database);
        parent::dropDatabase($database);
    }

    public function startDatabase(string $database): void
    {
        assert($this->_platform instanceof SQLAnywhere16Platform);
        $this->_execSql($this->_platform->getStartDatabaseSQL($database));
    }

    public function stopDatabase(string $database): void
    {
        assert($this->_platform instanceof SQLAnywhere16Platform);
        $this->_execSql($this->_platform->getStopDatabaseSQL($database));
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
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        return new Sequence($sequence['sequence_name'], $sequence['increment_by'], $sequence['start_with']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'])
            ?? $this->_platform->getDoctrineTypeMapping($tableColumn['type']);

        $precision = null;
        $scale     = null;
        $fixed     = false;
        $default   = null;

        if ($tableColumn['default'] !== null) {
            // Strip quotes from default value.
            $default = preg_replace(["/^'(.*)'$/", "/''/"], ['$1', "'"], $tableColumn['default']);

            if ($default === 'autoincrement') {
                $default = null;
            }
        }

        switch ($tableColumn['type']) {
            case 'binary':
            case 'char':
            case 'nchar':
                $fixed = true;
                break;
        }

        switch ($type) {
            case 'decimal':
            case 'float':
                $precision = $tableColumn['length'];
                $scale     = $tableColumn['scale'];
                break;
        }

        return new Column(
            $tableColumn['column_name'],
            Type::getType($type),
            [
                'length'        => $type === 'string' ? $tableColumn['length'] : null,
                'precision'     => $precision,
                'scale'         => $scale,
                'unsigned'      => (bool) $tableColumn['unsigned'],
                'fixed'         => $fixed,
                'notnull'       => (bool) $tableColumn['notnull'],
                'default'       => $default,
                'autoincrement' => (bool) $tableColumn['autoincrement'],
                'comment'       => $tableColumn['comment'] ?? '',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        return $table['table_name'];
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
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $foreignKeys = [];

        foreach ($tableForeignKeys as $tableForeignKey) {
            if (! isset($foreignKeys[$tableForeignKey['index_name']])) {
                $foreignKeys[$tableForeignKey['index_name']] = [
                    'local_columns'   => [$tableForeignKey['local_column']],
                    'foreign_table'   => $tableForeignKey['foreign_table'],
                    'foreign_columns' => [$tableForeignKey['foreign_column']],
                    'name'            => $tableForeignKey['index_name'],
                    'options'         => [
                        'notnull'           => $tableForeignKey['notnull'],
                        'match'             => $tableForeignKey['match'],
                        'onUpdate'          => $tableForeignKey['on_update'],
                        'onDelete'          => $tableForeignKey['on_delete'],
                        'check_on_commit'   => $tableForeignKey['check_on_commit'],
                        'clustered'         => $tableForeignKey['clustered'],
                        'for_olap_workload' => $tableForeignKey['for_olap_workload'],
                    ],
                ];
            } else {
                $foreignKeys[$tableForeignKey['index_name']]['local_columns'][]   = $tableForeignKey['local_column'];
                $foreignKeys[$tableForeignKey['index_name']]['foreign_columns'][] = $tableForeignKey['foreign_column'];
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
            $tableIndex['primary'] = (bool) $tableIndex['primary'];
            $tableIndex['flags']   = [];

            if ($tableIndex['clustered']) {
                $tableIndex['flags'][] = 'clustered';
            }

            if ($tableIndex['with_nulls_not_distinct']) {
                $tableIndex['flags'][] = 'with_nulls_not_distinct';
            }

            if (! $tableIndex['for_olap_workload']) {
                continue;
            }

            $tableIndex['flags'][] = 'for_olap_workload';
        }

        return parent::_getPortableTableIndexesList($tableIndexRows, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        $definition = preg_replace('/^.*\s+as\s+SELECT(.*)/i', 'SELECT$1', $view['view_def']);
        assert(is_string($definition));

        return new View($view['table_name'], $definition);
    }
}

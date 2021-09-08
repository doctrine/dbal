<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Types\Type;

use function array_change_key_case;
use function array_values;
use function is_string;
use function preg_match;
use function str_replace;
use function strpos;
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
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition(array $view): View
    {
        $view = array_change_key_case($view, CASE_LOWER);

        return new View($this->getQuotedIdentifierName($view['view_name']), $view['text']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableUserDefinition(array $user): array
    {
        $user = array_change_key_case($user, CASE_LOWER);

        return [
            'user' => $user['username'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableDefinition(array $table): string
    {
        $table = array_change_key_case($table, CASE_LOWER);

        return $this->getQuotedIdentifierName($table['table_name']);
    }

    /**
     * {@inheritdoc}
     *
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     */
    protected function _getPortableTableIndexesList(array $tableIndexes, string $tableName): array
    {
        $indexBuffer = [];
        foreach ($tableIndexes as $tableIndex) {
            $tableIndex = array_change_key_case($tableIndex, CASE_LOWER);

            $keyName = strtolower($tableIndex['name']);
            $buffer  = [];

            if ($tableIndex['is_primary'] === 'P') {
                $keyName              = 'primary';
                $buffer['primary']    = true;
                $buffer['non_unique'] = false;
            } else {
                $buffer['primary']    = false;
                $buffer['non_unique'] = ! $tableIndex['is_unique'];
            }

            $buffer['key_name']    = $keyName;
            $buffer['column_name'] = $this->getQuotedIdentifierName($tableIndex['column_name']);
            $indexBuffer[]         = $buffer;
        }

        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition(array $tableColumn): Column
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['data_type']);
        if (strpos($dbType, 'timestamp(') === 0) {
            if (strpos($dbType, 'with time zone') !== false) {
                $dbType = 'timestamptz';
            } else {
                $dbType = 'timestamp';
            }
        }

        $length = $precision = null;
        $scale  = 0;
        $fixed  = false;

        if (! isset($tableColumn['column_name'])) {
            $tableColumn['column_name'] = '';
        }

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

        $type = $this->extractDoctrineTypeFromComment($tableColumn['comments'])
            ?? $this->_platform->getDoctrineTypeMapping($dbType);

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

        if (isset($tableColumn['comments'])) {
            $options['comment'] = $tableColumn['comments'];
        }

        return new Column($this->getQuotedIdentifierName($tableColumn['column_name']), Type::getType($type), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList(array $tableForeignKeys): array
    {
        $list = [];
        foreach ($tableForeignKeys as $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            if (! isset($list[$value['constraint_name']])) {
                if ($value['delete_rule'] === 'NO ACTION') {
                    $value['delete_rule'] = null;
                }

                $list[$value['constraint_name']] = [
                    'name' => $this->getQuotedIdentifierName($value['constraint_name']),
                    'local' => [],
                    'foreign' => [],
                    'foreignTable' => $value['references_table'],
                    'onDelete' => $value['delete_rule'],
                ];
            }

            $localColumn   = $this->getQuotedIdentifierName($value['local_column']);
            $foreignColumn = $this->getQuotedIdentifierName($value['foreign_column']);

            $list[$value['constraint_name']]['local'][$value['position']]   = $localColumn;
            $list[$value['constraint_name']]['foreign'][$value['position']] = $foreignColumn;
        }

        $result = [];
        foreach ($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']),
                $this->getQuotedIdentifierName($constraint['foreignTable']),
                array_values($constraint['foreign']),
                $this->getQuotedIdentifierName($constraint['name']),
                ['onDelete' => $constraint['onDelete']]
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequenceDefinition(array $sequence): Sequence
    {
        $sequence = array_change_key_case($sequence, CASE_LOWER);

        return new Sequence(
            $this->getQuotedIdentifierName($sequence['sequence_name']),
            (int) $sequence['increment_by'],
            (int) $sequence['min_value']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableDatabaseDefinition(array $database): string
    {
        $database = array_change_key_case($database, CASE_LOWER);

        return $database['username'];
    }

    public function createDatabase(string $database): void
    {
        $statement = 'CREATE USER ' . $database;

        $params = $this->_conn->getParams();

        if (isset($params['password'])) {
            $statement .= ' IDENTIFIED BY ' . $params['password'];
        }

        $this->_conn->executeStatement($statement);

        $statement = 'GRANT DBA TO ' . $database;
        $this->_conn->executeStatement($statement);
    }

    /**
     * @throws Exception
     */
    protected function dropAutoincrement(string $table): bool
    {
        $sql = $this->_platform->getDropAutoincrementSql($table);
        foreach ($sql as $query) {
            $this->_conn->executeStatement($query);
        }

        return true;
    }

    public function dropTable(string $name): void
    {
        $this->tryMethod('dropAutoincrement', $name);

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
            return $this->_platform->quoteIdentifier($identifier);
        }

        return $identifier;
    }

    public function listTableDetails(string $name): Table
    {
        $table = parent::listTableDetails($name);

        $sql = $this->_platform->getListTableCommentsSQL($name);

        $tableOptions = $this->_conn->fetchAssociative($sql);

        if ($tableOptions !== false) {
            $table->addOption('comment', $tableOptions['COMMENTS']);
        }

        return $table;
    }
}

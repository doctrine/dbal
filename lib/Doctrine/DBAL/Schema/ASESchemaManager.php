<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Driver\ASE\ASEException;
use Doctrine\DBAL\Types\Type;

/**
 * ASE Schema Manager.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @author  Maximilian Ruta <mr@xtain.net>
 * @since   2.6
 */
class ASESchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequenceDefinition($sequence)
    {
        return new Sequence($sequence['name'], $sequence['increment'], $sequence['start_value']);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtok($tableColumn['type'], '(), ');
        $fixed = null;
        $length = (int) $tableColumn['length'];
        $default = $tableColumn['default'];

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        while ($default != ($default2 = preg_replace("/^\((.*)\)$/", '$1', $default))) {
            $default = trim($default2, "'");

            if ($default == 'getdate()') {
                $default = $this->_platform->getCurrentTimestampSQL();
            }
        }

        switch ($dbType) {
            case 'nchar':
            case 'nvarchar':
            case 'ntext':
                // Unicode data requires 2 bytes per character
                $length = $length / 2;
                break;
            case 'varchar':
                // TEXT type is returned as VARCHAR(MAX) with a length of -1
                if ($length == -1) {
                    $dbType = 'text';
                }
                break;
        }

        if ('char' === $dbType || 'nchar' === $dbType || 'binary' === $dbType) {
            $fixed = true;
        }

        $type                   = $this->_platform->getDoctrineTypeMapping($dbType);
        $type                   = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        $options = array(
            'length'        => ($length == 0 || !in_array($type, array('text', 'string'))) ? null : $length,
            'unsigned'      => false,
            'fixed'         => (bool) $fixed,
            'default'       => $default !== 'NULL' ? $default : null,
            'notnull'       => (bool) $tableColumn['notnull'],
            'scale'         => $tableColumn['scale'],
            'precision'     => $tableColumn['precision'],
            'autoincrement' => (bool) $tableColumn['autoincrement'],
            'comment'       => $tableColumn['comment'] !== '' ? $tableColumn['comment'] : null,
        );

        $column = new Column($tableColumn['name'], Type::getType($type), $options);

        if (isset($tableColumn['collation']) && $tableColumn['collation'] !== 'NULL') {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $foreignKeys = array();

        foreach ($tableForeignKeys as $tableForeignKey) {
            if ( ! isset($foreignKeys[$tableForeignKey['foreign_key']])) {
                $foreignKeys[$tableForeignKey['foreign_key']] = array(
                    'local_columns' => array($tableForeignKey['column_name']),
                    'foreign_table' => $tableForeignKey['reference_table_name'],
                    'foreign_columns' => array($tableForeignKey['reference_column_name']),
                    'name' => $tableForeignKey['foreign_key'],
                    'options' => array(
                        'onUpdate' => str_replace('_', ' ', $tableForeignKey['update_referential_action_desc']),
                        'onDelete' => str_replace('_', ' ', $tableForeignKey['delete_referential_action_desc'])
                    )
                );
            } else {
                $foreignKeys[$tableForeignKey['foreign_key']]['local_columns'][] = $tableForeignKey['column_name'];
                $foreignKeys[$tableForeignKey['foreign_key']]['foreign_columns'][] = $tableForeignKey['reference_column_name'];
            }
        }

        return parent::_getPortableTableForeignKeysList($foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName=null)
    {
        foreach ($tableIndexRows as &$tableIndex) {
            $tableIndex['non_unique'] = (boolean) $tableIndex['non_unique'];
            $tableIndex['primary'] = (boolean) $tableIndex['primary'];
            $tableIndex['flags'] = $tableIndex['flags'] ? array($tableIndex['flags']) : null;
        }

        return parent::_getPortableTableIndexesList($tableIndexRows, $tableName);
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
     */
    protected function getPortableNamespaceDefinition(array $namespace)
    {
        return $namespace['name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition($view)
    {
        // @todo
        return new View($view['name'], null);
    }

    /**
     * {@inheritdoc}
     */
    public function listTableIndexes($table)
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        try {
            $tableIndexes = $this->_conn->fetchAll($sql);
        } catch (\PDOException $e) {
            if ($e->getCode() == "IMSSP") {
                return array();
            } else {
                throw $e;
            }
        } catch (ASEException $e) {
            if (strpos($e->getMessage(), 'SQLSTATE [01000, 15472]') === 0) {
                return array();
            } else {
                throw $e;
            }
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
                $columnConstraintSql = $this->getColumnConstraintSQL($tableDiff->name, $col->getName());
                foreach ($this->_conn->fetchAll($columnConstraintSql) as $constraint) {
                    $this->_conn->exec("ALTER TABLE $tableDiff->name DROP CONSTRAINT " . $constraint['Name']);
                }
            }
        }

        parent::alterTable($tableDiff);
    }

    /**
     * Returns the SQL to retrieve the constraints for a given column.
     *
     * @param string $table
     * @param string $column
     *
     * @return string
     */
    private function getColumnConstraintSQL($table, $column)
    {
        $refWhere = "";

        for ($i = 1; $i <= $this->_platform->getMaxIndexFields(); $i++) {
            $refWhere .= "(
                SELECT substring(name,1,30)
                FROM syscolumns
                WHERE id=ref.reftabid AND colid=ref.refkey" . $i . "
            ) = " . $this->_conn->quote($column) . " OR ";
        }

        $refWhere = rtrim($refWhere, " OR ");

        return "SELECT const.name
                FROM sysobjects tab
                INNER JOIN sysreferences ref ON ref.tableid = tab.id
                INNER JOIN sysobjects const ON const.id= ref.constrid
                WHERE tab.type = 'U' AND tab.name = " . $this->_conn->quote($table) . " AND (" . $refWhere . ")";
    }
}

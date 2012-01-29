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
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Events;

/**
 * SQL Server Schema Manager
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Juozas Kaziukenas <juozas@juokaz.com>
 * @since       2.0
 */
class SQLServerSchemaManager extends AbstractSchemaManager
{
    /**
     * @override
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtolower($tableColumn['TYPE_NAME']);

        $autoincrement = false;
        if (stripos($dbType, 'identity')) {
            $dbType = trim(str_ireplace('identity', '', $dbType));
            $autoincrement = true;
        }

        $type = array();
        $unsigned = $fixed = null;

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $default = $tableColumn['COLUMN_DEF'];

        while ($default != ($default2 = preg_replace("/^\((.*)\)$/", '$1', $default))) {
            $default = trim($default2, "'");
        }

        $length = (int) $tableColumn['LENGTH'];

        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        switch ($type) {
            case 'char':
                if ($tableColumn['LENGTH'] == '1') {
                    $type = 'boolean';
                    if (preg_match('/^(is|has)/', $tableColumn['name'])) {
                        $type = array_reverse($type);
                    }
                }
                $fixed = true;
                break;
            case 'text':
                $fixed = false;
                break;
        }
        switch ($dbType) {
            case 'nchar':
            case 'nvarchar':
            case 'ntext':
                // Unicode data requires 2 bytes per character
                $length = $length / 2;
                break;
        }

        $options = array(
            'length' => ($length == 0 || !in_array($type, array('text', 'string'))) ? null : $length,
            'unsigned' => (bool) $unsigned,
            'fixed' => (bool) $fixed,
            'default' => $default !== 'NULL' ? $default : null,
            'notnull' => (bool) ($tableColumn['IS_NULLABLE'] != 'YES'),
            'scale' => $tableColumn['SCALE'],
            'precision' => $tableColumn['PRECISION'],
            'autoincrement' => $autoincrement,
        );

        return new Column($tableColumn['COLUMN_NAME'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    /**
     * @override
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName=null)
    {
        $result = array();
        foreach ($tableIndexRows AS $tableIndex) {
            $indexName = $keyName = $tableIndex['index_name'];
            if (strpos($tableIndex['index_description'], 'primary key') !== false) {
                $keyName = 'primary';
            }
            $keyName = strtolower($keyName);

            $result[$keyName] = array(
                'name' => $indexName,
                'columns' => explode(', ', $tableIndex['index_keys']),
                'unique' => strpos($tableIndex['index_description'], 'unique') !== false,
                'primary' => strpos($tableIndex['index_description'], 'primary key') !== false,
            );
        }

        $eventManager = $this->_platform->getEventManager();

        $indexes = array();
        foreach ($result AS $indexKey => $data) {
            $index = null;
            $defaultPrevented = false;

            if (null !== $eventManager && $eventManager->hasListeners(Events::onSchemaIndexDefinition)) {
                $eventArgs = new SchemaIndexDefinitionEventArgs($data, $tableName, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaIndexDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $index = $eventArgs->getIndex();
            }

            if (!$defaultPrevented) {
                $index = new Index($data['name'], $data['columns'], $data['unique'], $data['primary']);
            }

            if ($index) {
                $indexes[$indexKey] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @override
     */
    public function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        return new ForeignKeyConstraint(
                (array) $tableForeignKey['ColumnName'],
                $tableForeignKey['ReferenceTableName'],
                (array) $tableForeignKey['ReferenceColumnName'],
                $tableForeignKey['ForeignKey'],
                array(
                    'onUpdate' => str_replace('_', ' ', $tableForeignKey['update_referential_action_desc']),
                    'onDelete' => str_replace('_', ' ', $tableForeignKey['delete_referential_action_desc']),
                )
        );
    }

    /**
     * @override
     */
    protected function _getPortableTableDefinition($table)
    {
        return $table['name'];
    }

    /**
     * @override
     */
    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['name'];
    }

    /**
     * @override
     */
    protected function _getPortableViewDefinition($view)
    {
        // @todo
        return new View($view['name'], null);
    }

    /**
     * List the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @param string $table The name of the table
     * @return Index[] $tableIndexes
     */
    public function listTableIndexes($table)
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->_conn->getDatabase());

        try {
            $tableIndexes = $this->_conn->fetchAll($sql);
        } catch(\PDOException $e) {
            if ($e->getCode() == "IMSSP") {
                return array();
            } else {
                throw $e;
            }
        }

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }
}

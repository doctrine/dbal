<?php
/*
 *  $Id$
 *
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

/**
 * xxx
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Juozas Kaziukenas <juozas@juokaz.com>
 * @version     $Revision$
 * @since       2.0
 */
class MsSqlSchemaManager extends AbstractSchemaManager
{
    /**
     * @override
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtolower($tableColumn['TYPE_NAME']);

        if (stripos($dbType, 'identity')) {
            $dbType = trim(str_ireplace('identity', '', $dbType));
        }

        $type = array();
        $unsigned = $fixed = null;

        if ( ! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }
        
        // Map db type to Doctrine mapping type
        switch ($dbType) {
            case 'tinyint':
                $type = 'boolean';
                break;
            case 'smallint':
                $type = 'smallint';
                break;
            case 'mediumint':
                $type = 'integer';
                break;
            case 'int':
            case 'integer':
                $type = 'integer';
                break;
            case 'bigint':
                $type = 'bigint';
                break;
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
            case 'text':
                $type = 'text';
                $fixed = false;
                break;
            case 'varchar':
                $fixed = false;
            case 'string':
            case 'char':
                $type = 'string';
                if ($tableColumn['LENGTH'] == '1') {
                    $type = 'boolean';
                    if (preg_match('/^(is|has)/', $tableColumn['name'])) {
                        $type = array_reverse($type);
                    }
                } else if (strstr($dbType, 'text')) {
                    $type = 'text';
                    if ($decimal == 'binary') {
                        $type = 'blob';
                    }
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                break;
            case 'set':
                $fixed = false;
                $type = 'text';
                $type = 'integer'; //FIXME:???
                break;
            case 'date':
                $type = 'date';
                break;
            case 'datetime':
            case 'timestamp':
                $type = 'datetime';
                break;
            case 'time':
                $type = 'time';
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                $type = 'decimal';
                break;
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
            case 'binary':
            case 'varbinary':
                $type = 'blob';
                break;
            case 'year':
                $type = 'integer';
                $type = 'date';
                break;
            case 'geometry':
            case 'geometrycollection':
            case 'point':
            case 'multipoint':
            case 'linestring':
            case 'multilinestring':
            case 'polygon':
            case 'multipolygon':
                $type = 'blob';
                break;
            default:
                $type = 'string';
        }
        
        $def =  array(
            'type' => $type,
            'length' => ((int) $tableColumn['LENGTH'] == 0) ? null : (int) $tableColumn['LENGTH'],
            'unsigned' => (bool) $unsigned,
            'fixed' => (bool) $fixed
        );


        $default = $tableColumn['COLUMN_DEF'];

        while($default != ($default2 = preg_replace("/^\((.*)\)$/", '$1', $default))) {
            $default = $default2;
        }
        
        $options = array(
            'length'        => ((int) $tableColumn['LENGTH'] == 0) ? null : (int) $tableColumn['LENGTH'],
            'unsigned'      => (bool)$unsigned,
            'fixed'         => (bool)$fixed,
            'default'       => $default !== 'NULL' ? $default : null,
            'notnull'       => (bool) ($tableColumn['IS_NULLABLE'] != 'YES'),
            'scale'         => $tableColumn['SCALE'],
            'precision'     => $tableColumn['PRECISION'],
            'platformOptions' => array(
                // @todo
                'primary' =>  false,
                'unique' => false,
                'autoincrement' => false,
            ),
        );

        return new Column($tableColumn['COLUMN_NAME'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    /**
     * @override
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName=null)
    {
        $result = array();
        foreach($tableIndexRows AS $tableIndex) {
            $indexName = $keyName = $tableIndex['index_name'];
            if(strpos($tableIndex['index_description'], 'primary key') !== false) {
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

        $indexes = array();
        foreach($result AS $indexKey => $data) {
            $indexes[$indexKey] = new Index($data['name'], $data['columns'], $data['unique'], $data['primary']);
        }

        return $indexes;
    }

    /**
     * @override
     */
    public function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        return new ForeignKeyConstraint(
            (array)$tableForeignKey['ColumnName'],
            $tableForeignKey['ReferenceTableName'],
            (array)$tableForeignKey['ReferenceColumnName'],
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
}
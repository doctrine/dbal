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

/**
 * Schema manager for the MySql RDBMS.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @version     $Revision$
 * @since       2.0
 */
class MySqlSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableViewDefinition($view)
    {
        return new View($view['TABLE_NAME'], $view['VIEW_DEFINITION']);
    }

    protected function _getPortableTableDefinition($table)
    {
        return array_shift($table);
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['User'],
            'password' => $user['Password'],
        );
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        foreach($tableIndexes as $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            if($v['key_name'] == 'PRIMARY') {
                $v['primary'] = true;
            } else {
                $v['primary'] = false;
            }
            if (strpos($v['index_type'], 'FULLTEXT') !== false) {
                $v['flags'] = array('FULLTEXT');
            }
            $tableIndexes[$k] = $v;
        }

        return parent::_getPortableTableIndexesList($tableIndexes, $tableName);
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return end($sequence);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['Database'];
    }

    /**
     * Gets a portable column definition.
     *
     * The database type is mapped to a corresponding Doctrine mapping type.
     *
     * @param $tableColumn
     * @return array
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');
        if (isset($tableColumn['length'])) {
            $length = $tableColumn['length'];
            $decimal = '';
        } else {
            $length = strtok('(), ');
            $decimal = strtok('(), ') ? strtok('(), '):null;
        }
        $type = array();
        $fixed = null;

        if ( ! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $scale = null;
        $precision = null;

        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        switch ($dbType) {
            case 'char':
                $fixed = true;
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                if(preg_match('([A-Za-z]+\(([0-9]+)\,([0-9]+)\))', $tableColumn['type'], $match)) {
                    $precision = $match[1];
                    $scale = $match[2];
                    $length = null;
                }
                break;
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
            case 'year':
                $length = null;
                break;
        }

        $length = ((int) $length == 0) ? null : (int) $length;

        $options = array(
            'length'        => $length,
            'unsigned'      => (bool) (strpos($tableColumn['type'], 'unsigned') !== false),
            'fixed'         => (bool) $fixed,
            'default'       => isset($tableColumn['default']) ? $tableColumn['default'] : null,
            'notnull'       => (bool) ($tableColumn['null'] != 'YES'),
            'scale'         => null,
            'precision'     => null,
            'autoincrement' => (bool) (strpos($tableColumn['extra'], 'auto_increment') !== false),
            'comment'       => (isset($tableColumn['comment'])) ? $tableColumn['comment'] : null
        );

        if ($scale !== null && $precision !== null) {
            $options['scale'] = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['field'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = array();
        foreach ($tableForeignKeys as $key => $value) {
            $value = array_change_key_case($value, CASE_LOWER);
            if (!isset($list[$value['constraint_name']])) {
                if (!isset($value['delete_rule']) || $value['delete_rule'] == "RESTRICT") {
                    $value['delete_rule'] = null;
                }
                if (!isset($value['update_rule']) || $value['update_rule'] == "RESTRICT") {
                    $value['update_rule'] = null;
                }

                $list[$value['constraint_name']] = array(
                    'name' => $value['constraint_name'],
                    'local' => array(),
                    'foreign' => array(),
                    'foreignTable' => $value['referenced_table_name'],
                    'onDelete' => $value['delete_rule'],
                    'onUpdate' => $value['update_rule'],
                );
            }
            $list[$value['constraint_name']]['local'][] = $value['column_name'];
            $list[$value['constraint_name']]['foreign'][] = $value['referenced_column_name'];
        }

        $result = array();
        foreach($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']), $constraint['foreignTable'],
                array_values($constraint['foreign']), $constraint['name'],
                array(
                    'onDelete' => $constraint['onDelete'],
                    'onUpdate' => $constraint['onUpdate'],
                )
            );
        }

        return $result;
    }
}

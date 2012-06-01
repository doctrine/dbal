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
 * and is licensed under the MIT license. For more information, see
 * <http://www.phpdoctrine.org>.
 */

namespace Doctrine\DBAL\Schema;

/**
 * Oracle Schema Manager
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @version     $Revision$
 * @since       2.0
 */
class OracleSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableViewDefinition($view)
    {
        $view = \array_change_key_case($view, CASE_LOWER);

        return new View($view['view_name'], $view['text']);
    }

    protected function _getPortableUserDefinition($user)
    {
        $user = \array_change_key_case($user, CASE_LOWER);

        return array(
            'user' => $user['username'],
        );
    }

    protected function _getPortableTableDefinition($table)
    {
        $table = \array_change_key_case($table, CASE_LOWER);

        return $table['table_name'];
    }

    /**
     * @license New BSD License
     * @link http://ezcomponents.org/docs/api/trunk/DatabaseSchema/ezcDbSchemaPgsqlReader.html
     * @param  array $tableIndexes
     * @param  string $tableName
     * @return array
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        $indexBuffer = array();
        foreach ( $tableIndexes as $tableIndex ) {
            $tableIndex = \array_change_key_case($tableIndex, CASE_LOWER);

            $keyName = strtolower($tableIndex['name']);

            if ( strtolower($tableIndex['is_primary']) == "p" ) {
                $keyName = 'primary';
                $buffer['primary'] = true;
                $buffer['non_unique'] = false;
            } else {
                $buffer['primary'] = false;
                $buffer['non_unique'] = ( $tableIndex['is_unique'] == 0 ) ? true : false;
            }
            $buffer['key_name'] = $keyName;
            $buffer['column_name'] = $tableIndex['column_name'];
            $indexBuffer[] = $buffer;
        }
        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = \array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['data_type']);
        if(strpos($dbType, "timestamp(") === 0) {
            if (strpos($dbType, "WITH TIME ZONE")) {
                $dbType = "timestamptz";
            } else {
                $dbType = "timestamp";
            }
        }

        $type = array();
        $length = $unsigned = $fixed = null;
        if ( ! empty($tableColumn['data_length'])) {
            $length = $tableColumn['data_length'];
        }

        if ( ! isset($tableColumn['column_name'])) {
            $tableColumn['column_name'] = '';
        }

        if (stripos($tableColumn['data_default'], 'NULL') !== null) {
            $tableColumn['data_default'] = null;
        }

        $precision = null;
        $scale = null;

        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comments'], $type);
        $tableColumn['comments'] = $this->removeDoctrineTypeFromComment($tableColumn['comments'], $type);

        switch ($dbType) {
            case 'number':
                if ($tableColumn['data_precision'] == 20 && $tableColumn['data_scale'] == 0) {
                    $precision = 20;
                    $scale = 0;
                    $type = 'bigint';
                } elseif ($tableColumn['data_precision'] == 5 && $tableColumn['data_scale'] == 0) {
                    $type = 'smallint';
                    $precision = 5;
                    $scale = 0;
                } elseif ($tableColumn['data_precision'] == 1 && $tableColumn['data_scale'] == 0) {
                    $precision = 1;
                    $scale = 0;
                    $type = 'boolean';
                } elseif ($tableColumn['data_scale'] > 0) {
                    $precision = $tableColumn['data_precision'];
                    $scale = $tableColumn['data_scale'];
                    $type = 'decimal';
                }
                $length = null;
                break;
            case 'pls_integer':
            case 'binary_integer':
                $length = null;
                break;
            case 'varchar':
            case 'varchar2':
            case 'nvarchar2':
                $length = $tableColumn['char_length'];
                $fixed = false;
                break;
            case 'char':
            case 'nchar':
                $length = $tableColumn['char_length'];
                $fixed = true;
                break;
            case 'date':
            case 'timestamp':
                $length = null;
                break;
            case 'float':
                $precision = $tableColumn['data_precision'];
                $scale = $tableColumn['data_scale'];
                $length = null;
                break;
            case 'clob':
            case 'nclob':
                $length = null;
                break;
            case 'blob':
            case 'raw':
            case 'long raw':
            case 'bfile':
                $length = null;
                break;
            case 'rowid':
            case 'urowid':
            default:
                $length = null;
        }

        $options = array(
            'notnull'    => (bool) ($tableColumn['nullable'] === 'N'),
            'fixed'      => (bool) $fixed,
            'unsigned'   => (bool) $unsigned,
            'default'    => $tableColumn['data_default'],
            'length'     => $length,
            'precision'  => $precision,
            'scale'      => $scale,
            'comment'       => (isset($tableColumn['comments'])) ? $tableColumn['comments'] : null,
            'platformDetails' => array(),
        );

        return new Column($tableColumn['column_name'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = array();
        foreach ($tableForeignKeys as $value) {
            $value = \array_change_key_case($value, CASE_LOWER);
            if (!isset($list[$value['constraint_name']])) {
                if ($value['delete_rule'] == "NO ACTION") {
                    $value['delete_rule'] = null;
                }

                $list[$value['constraint_name']] = array(
                    'name' => $value['constraint_name'],
                    'local' => array(),
                    'foreign' => array(),
                    'foreignTable' => $value['references_table'],
                    'onDelete' => $value['delete_rule'],
                );
            }
            $list[$value['constraint_name']]['local'][$value['position']] = $value['local_column'];
            $list[$value['constraint_name']]['foreign'][$value['position']] = $value['foreign_column'];
        }

        $result = array();
        foreach($list as $constraint) {
            $result[] = new ForeignKeyConstraint(
                array_values($constraint['local']), $constraint['foreignTable'],
                array_values($constraint['foreign']),  $constraint['name'],
                array('onDelete' => $constraint['onDelete'])
            );
        }

        return $result;
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        $sequence = \array_change_key_case($sequence, CASE_LOWER);
        return new Sequence($sequence['sequence_name'], $sequence['increment_by'], $sequence['min_value']);
    }

    protected function _getPortableFunctionDefinition($function)
    {
        $function = \array_change_key_case($function, CASE_LOWER);
        return $function['name'];
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        $database = \array_change_key_case($database, CASE_LOWER);
        return $database['username'];
    }

    public function createDatabase($database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $username   = $database;
        $password   = $params['password'];

        $query  = 'CREATE USER ' . $username . ' IDENTIFIED BY ' . $password;
        $result = $this->_conn->executeUpdate($query);

        $query = 'GRANT CREATE SESSION, CREATE TABLE, UNLIMITED TABLESPACE, CREATE SEQUENCE, CREATE TRIGGER TO ' . $username;
        $result = $this->_conn->executeUpdate($query);

        return true;
    }

    public function dropAutoincrement($table)
    {
        $sql = $this->_platform->getDropAutoincrementSql($table);
        foreach ($sql as $query) {
            $this->_conn->executeUpdate($query);
        }

        return true;
    }

    public function dropTable($name)
    {
        $this->dropAutoincrement($name);

        return parent::dropTable($name);
    }
}

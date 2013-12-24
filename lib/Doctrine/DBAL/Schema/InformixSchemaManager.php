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

use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Events;

/**
 * Informix Schema Manager.
 *
 * @author Jose M. Alonso M.  <josemalonsom@yahoo.es>
 * @link   www.doctrine-project.org
 */
class InformixSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     */
    public function listTableNames()
    {
        $sql = $this->_platform->getListTablesSQL();
        $sql .= " AND UPPER(OWNER) = UPPER('".$this->_conn->getUsername()."')";

        $tables = $this->_conn->fetchAll($sql);

        return $this->_getPortableTablesList($tables);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $autoincrement   = false;
        $fixed           = false;
        $unsigned        = false;
        $length          = null;
        $scale           = null;
        $precision       = null;
        $platformOptions = array();

        $tableColumn = array_change_key_case($tableColumn, \CASE_LOWER);

        switch ( strtolower($tableColumn['typename']) ) {

            case 'char':
            case 'nchar':
                $fixed  = true;
                $length = $tableColumn['collength'];
            break;

            case 'lvarchar':
                $length = $tableColumn['collength'];
            break;

            case 'varchar':
            case 'nvarchar':
                $length = $tableColumn['maxlength'];
                $platformOptions = array(
                                       'maxlength' => $tableColumn['maxlength'],
                                       'minlength' => $tableColumn['minlength']
                                   );
            break;

            case 'decimal':
            case 'money':
                $scale     = $tableColumn['scale'];
                $precision = $tableColumn['precision'];
            break;

            case 'bigserial':
            case 'serial8':
            case 'serial':
                $autoincrement = true;
            break;

            case 'datetime':
                if ( $tableColumn['collength'] == 1642 ) {
                    $tableColumn['typename'] = 'time';
                }
            break;

        }

        $default = $this->_getColumnDefinitionDefault($tableColumn['typename'],
            $tableColumn['typedefault'], $tableColumn['default']);

        $options = array(
            'autoincrement'   => $autoincrement,
            'default'         => $default,
            'fixed'           => $fixed,
            'length'          => $length,
            'notnull'         => ($tableColumn['nulls'] == 'N'),
            'platformOptions' => $platformOptions,
            'precision'       => $precision,
            'scale'           => $scale,
            'unsigned'        => $unsigned,
        );

        $type = $this->_platform->getDoctrineTypeMapping($tableColumn['typename']);

        return new Column($tableColumn['colname'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    /**
     * Returns the default value of a column.
     *
     * @param string data type name
     * @param string type of default value
     * @param string default value
     * @return null|string
     * @link http://pic.dhe.ibm.com/infocenter/idshelp/v115/topic/com.ibm.sqlr.doc/ids_sqr_030.htm
     */
    protected function _getColumnDefinitionDefault($typeName, $typeDefault, $defaultValue)
    {

        switch ( $typeDefault ) {

            case 'C':
                $default = 'CURRENT';
            break;

            case 'L':
                if ( preg_match('/char/i', $typeName) ) {
                    $default = trim($defaultValue);
                }
                elseif ( 'boolean' == $typeName ) {
                     $default = (strtoupper(substr($defaultValue, 0, 1)) == 'T');
                }
                else {
                    $default = trim(preg_replace('/^.*?\s/', '', $defaultValue, 1));
                }
            break;

            case 'T':
                $default = 'TODAY';
            break;

            default:
                $default = null;
        }

        return $default;

    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTablesList($tables)
    {
        $tableNames = array();

        foreach ( $tables as $tableRow ) {
            $tableRow = array_change_key_case($tableRow, \CASE_LOWER);
            $tableNames[] = $tableRow['tabname'];
        }

        return $tableNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {

        $indexesByColumns = array();

        foreach( $tableIndexes as $k => $v ) {

            $v = array_change_key_case($v, CASE_LOWER);

            foreach ( range(1,16) as $i ) {

              if ( ! empty($v["col$i"]) ) {

                  $indexesByColumns[] = array(
                      'column_name' => $v["col$i"],
                      'key_name'    => $v['constrname'] ? : $v['idxname'],
                      'non_unique'  => $v['idxtype'] == 'D',
                      'primary'     => $v['constrtype'] == 'P',
                  );

              }

            }

        }

        return parent::_getPortableTableIndexesList($indexesByColumns, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {

        $tableForeignKey = array_change_key_case($tableForeignKey, CASE_LOWER);

        $fkColnames = $pkColnames = array();

        foreach ( range(1, 16) as $i ) {

            if ( ! empty($tableForeignKey["col$i"]) ) {
                $fkColnames[] = $tableForeignKey["col$i"];
            }

            if ( ! empty($tableForeignKey["pkcol$i"]) ) {
                $pkColnames[] = $tableForeignKey["pkcol$i"];
            }

        }

        $tableForeignKey['delrule'] = $this->_getPortableForeignKeyRuleDef($tableForeignKey['delrule']);
        $tableForeignKey['updrule'] = $this->_getPortableForeignKeyRuleDef($tableForeignKey['updrule']);

        return new ForeignKeyConstraint(
            array_map('trim', $fkColnames),
            $tableForeignKey['reftabname'],
            array_map('trim', $pkColnames),
            $tableForeignKey['constrname'],
            array(
                'onDelete' => $tableForeignKey['delrule'],
                'onUpdate' => $tableForeignKey['updrule'],
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableForeignKeyRuleDef($def)
    {
        if ( $def == "C" ) {
            return "CASCADE";
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewsList($views)
    {
        $viewsParts = array();

        foreach ( $views as $value ) {

            $value = array_change_key_case($value, \CASE_LOWER);

            $viewName = $value['viewname'];

            if ( ! isset($viewsParts[$viewName]) ) {
                $viewsParts[$viewName] = array();
            }

            $viewsParts[$viewName][$value['seqno']] = $value['viewtext'];

        }

        $list = array();

        foreach ( $viewsParts as $viewName => $viewSql ) {

            ksort($viewSql, \SORT_NUMERIC);
            $viewSql = implode('', $viewSql);

            $view = new View($viewName, $viewSql);

            $viewName = strtolower($view->getQuotedName($this->_platform));

            $list[$viewName] = $view;

        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableSequenceDefinition($sequence)
    {
        $sequence = array_change_key_case($sequence, \CASE_LOWER);

        return new Sequence(
            $sequence['sequence'], $sequence['inc_val'], $sequence['start_val']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableDatabaseDefinition($database) {

        $database = array_change_key_case($database, \CASE_LOWER);

        return strtolower(trim($database['name']));
    }
}

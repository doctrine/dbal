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
 * IBMi Db2 Schema Manager.
 *
 * @link   www.doctrine-project.org
 * @since  1.0
 * @author Cassiano Vailati <c.vailati@esconsulting.it> extending work of Benjamin Eberlei <kontakt@beberlei.de>
 */
class DB2iSeriesSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     */

    public function listTableNames()
    {
        $sql = $this->_platform->getListTablesSQL($this->getDatabase());

        $tables = $this->_conn->fetchAll($sql);
        $tableNames = $this->_getPortableTablesList($tables);

        return $this->filterAssetNames($tableNames);
    }


    /**
     * Lists the available sequences for this connection.
     *
     * @param string|null $database
     *
     * @return \Doctrine\DBAL\Schema\Sequence[]
     */
    public function listSequences($database = null)
    {
        if (is_null($database)) {
            $database = $this->getDatabase();
        }
        $sql = $this->_platform->getListSequencesSQL($database);

        $sequences = $this->_conn->fetchAll($sql);

        return $this->filterAssetNames($this->_getPortableSequencesList($sequences));
    }

    /**
     * Lists the columns for a given table.
     *
     * In contrast to other libraries and to the old version of Doctrine,
     * this column definition does try to contain the 'primary' field for
     * the reason that it is not portable accross different RDBMS. Use
     * {@see listTableIndexes($tableName)} to retrieve the primary key
     * of a table. We're a RDBMS specifies more details these are held
     * in the platformDetails array.
     *
     * @param string      $table    The name of the table.
     * @param string|null $database
     *
     * @return \Doctrine\DBAL\Schema\Column[]
     */
    public function listTableColumns($table, $database = null)
    {
        if ( ! $database) {
            $database = $this->getDatabase();
        }

        $sql = $this->_platform->getListTableColumnsSQL($table, $database);

        $tableColumns = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableColumnList($table, $database, $tableColumns);
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @param string $table The name of the table.
     *
     * @return \Doctrine\DBAL\Schema\Index[]
     */
    public function listTableIndexes($table)
    {
        $sql = $this->_platform->getListTableIndexesSQL($table, $this->getDatabase());

        $tableIndexes = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * Lists the views this connection has.
     *
     * @return \Doctrine\DBAL\Schema\View[]
     */
    public function listViews()
    {
        $database = $this->getDatabase();
        $sql = $this->_platform->getListViewsSQL($database);
        $views = $this->_conn->fetchAll($sql);

        return $this->_getPortableViewsList($views);
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @param string      $table    The name of the table.
     * @param string|null $database
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function listTableForeignKeys($table, $database = null)
    {
        if (is_null($database)) {
            $database = $this->getDatabase();
        }
        $sql = $this->_platform->getListTableForeignKeysSQL($table, $database);
        $tableForeignKeys = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, \CASE_LOWER);

        $length = null;
        $fixed = null;
        $unsigned = false;
        $scale = false;
        $precision = false;

        $default = null;

        if (null !== $tableColumn['default'] && 'NULL' != $tableColumn['default']) {
            $default = trim($tableColumn['default'], "'");
        }

        $type = $this->_platform->getDoctrineTypeMapping($tableColumn['typename']);

        $length = $tableColumn['length'];

        switch (strtolower($tableColumn['typename'])) {
            case 'smallint':
                break;
            case 'bigint':
                break;
            case 'integer':
                break;
            case 'time':
                break;
            case 'date':
                break;
            case 'string':
                $fixed = true;
                break;
            case 'binary':
                break;
            case 'text':
                break;
            case 'blob':
                break;
            case 'decimal':
                $scale = $tableColumn['scale'];
                $precision = $tableColumn['length'];
                break;
            case 'float':
                $scale = $tableColumn['scale'];
                $precision = $tableColumn['length'];
                break;
            case 'datetime':
                break;
            default:
        }

        $options = array(
            'length'        => $length,
            'unsigned'      => (bool) $unsigned,
            'fixed'         => (bool) $fixed,
            'default'       => $default,
            'autoincrement' => (boolean) $tableColumn['autoincrement'],
            'notnull'       => (bool) ($tableColumn['nulls'] == 'N'),
            'scale'         => null,
            'precision'     => null,
            'platformOptions' => array(),
        );

        if ($scale !== null && $precision !== null) {
            $options['scale'] = $scale;
            $options['precision'] = $precision;
        }

        return new Column($tableColumn['colname'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTablesList($tables)
    {
        $tableNames = array();
        foreach ($tables as $tableRow) {
            $tableRow = array_change_key_case($tableRow, \CASE_LOWER);
            $tableNames[] = $tableRow['name'];
        }

        return $tableNames;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName = null)
    {
        foreach ($tableIndexRows as &$tableIndexRow) {
            $tableIndexRow = array_change_key_case($tableIndexRow, \CASE_LOWER);
            $tableIndexRow['primary'] = (boolean) $tableIndexRow['primary'];
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
    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $foreignKeys = array();

        foreach ($tableForeignKeys as $tableForeignKey) {
            $tableForeignKey = array_change_key_case($tableForeignKey, \CASE_LOWER);

            if (!isset($foreignKeys[$tableForeignKey['index_name']])) {
                $foreignKeys[$tableForeignKey['index_name']] = array(
                    'local_columns'   => array($tableForeignKey['local_column']),
                    'foreign_table'   => $tableForeignKey['foreign_table'],
                    'foreign_columns' => array($tableForeignKey['foreign_column']),
                    'name'            => $tableForeignKey['index_name'],
                    'options'         => array(
                        'onUpdate' => $tableForeignKey['on_update'],
                        'onDelete' => $tableForeignKey['on_delete'],
                    )
                );
            } else {
                $foreignKeys[$tableForeignKey['index_name']]['local_columns'][] = $tableForeignKey['local_column'];
                $foreignKeys[$tableForeignKey['index_name']]['foreign_columns'][] = $tableForeignKey['foreign_column'];
            }
        }

        return parent::_getPortableTableForeignKeysList($foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableForeignKeyRuleDef($def)
    {
        if ($def == "C") {
            return "CASCADE";
        } elseif ($def == "N") {
            return "SET NULL";
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function _getPortableViewDefinition($view)
    {
        $view = array_change_key_case($view, \CASE_LOWER);
        // sadly this still segfaults on PDO_IBM, see http://pecl.php.net/bugs/bug.php?id=17199
        //$view['text'] = (is_resource($view['text']) ? stream_get_contents($view['text']) : $view['text']);
        if (!is_resource($view['text'])) {
            $pos = strpos($view['text'], ' AS ');
            $sql = substr($view['text'], $pos+4);
        } else {
            $sql = '';
        }

        return new View($view['name'], $sql);
    }

    /**
     * Returns database name
     */
    protected function getDatabase()
    {
        //In iSeries systems, with SQL naming, the default database name is specified in driverOptions['i5_lib']
        $dbParams = $this->_conn->getParams();
        if(array_key_exists('driverOptions', $dbParams) && array_key_exists('i5_lib', $dbParams['driverOptions']))
        {
            return $dbParams['driverOptions']['i5_lib'];
        }
        else
        {
            return null;
        }
    }
}

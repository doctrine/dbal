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
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Schema;

/**
 * xxx
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @version     $Revision$
 * @since       2.0
 */
class PostgreSqlSchemaManager extends AbstractSchemaManager
{

    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        $onUpdate = null;
        $onDelete = null;

        if (preg_match('(ON UPDATE ([a-zA-Z0-9]+))', $tableForeignKey['condef'], $match)) {
            $onUpdate = $match[1];
        }
        if (preg_match('(ON DELETE ([a-zA-Z0-9]+))', $tableForeignKey['condef'], $match)) {
            $onDelete = $match[1];
        }

        if (preg_match('/FOREIGN KEY \((.+)\) REFERENCES (.+)\((.+)\)/', $tableForeignKey['condef'], $values)) {
            // PostgreSQL returns identifiers that are keywords with quotes, we need them later, don't get
            // the idea to trim them here.
            $localColumns = array_map('trim', explode(",", $values[1]));
            $foreignColumns = array_map('trim', explode(",", $values[3]));
            $foreignTable = $values[2];
        }

        return new ForeignKeyConstraint(
                $localColumns, $foreignTable, $foreignColumns, $tableForeignKey['conname'],
                array('onUpdate' => $onUpdate, 'onDelete' => $onDelete)
        );
    }

    public function dropDatabase($database)
    {
        $params = $this->_conn->getParams();
        $params["dbname"] = "postgres";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::dropDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
    }

    public function createDatabase($database)
    {
        $params = $this->_conn->getParams();
        $params["dbname"] = "postgres";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::createDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
    }

    protected function _getPortableTriggerDefinition($trigger)
    {
        return $trigger['trigger_name'];
    }

    protected function _getPortableViewDefinition($view)
    {
        return new View($view['viewname'], $view['definition']);
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['usename'],
            'password' => $user['passwd']
        );
    }

    protected function _getPortableTableDefinition($table)
    {
        if ($table['schema_name'] == 'public') {
            return $table['table_name'];
        } else {
            return $table['schema_name'] . "." . $table['table_name'];
        }
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
        $buffer = array();
        foreach ($tableIndexes AS $row) {
            $colNumbers = explode(' ', $row['indkey']);
            $colNumbersSql = 'IN (' . join(' ,', $colNumbers) . ' )';
            $columnNameSql = "SELECT attnum, attname FROM pg_attribute
                WHERE attrelid={$row['indrelid']} AND attnum $colNumbersSql ORDER BY attnum ASC;";

            $stmt = $this->_conn->executeQuery($columnNameSql);
            $indexColumns = $stmt->fetchAll();

            // required for getting the order of the columns right.
            foreach ($colNumbers AS $colNum) {
                foreach ($indexColumns as $colRow) {
                    if ($colNum == $colRow['attnum']) {
                        $buffer[] = array(
                            'key_name' => $row['relname'],
                            'column_name' => trim($colRow['attname']),
                            'non_unique' => !$row['indisunique'],
                            'primary' => $row['indisprimary']
                        );
                    }
                }
            }
        }
        return parent::_getPortableTableIndexesList($buffer);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['datname'];
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        if ($sequence['schemaname'] != 'public') {
            $sequenceName = $sequence['schemaname'] . "." . $sequence['relname'];
        } else {
            $sequenceName = $sequence['relname'];
        }

        $data = $this->_conn->fetchAll('SELECT min_value, increment_by FROM ' . $sequenceName);
        return new Sequence($sequenceName, $data[0]['increment_by'], $data[0]['min_value']);
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        if (strtolower($tableColumn['type']) === 'varchar') {
            // get length from varchar definition
            $length = preg_replace('~.*\(([0-9]*)\).*~', '$1', $tableColumn['complete_type']);
            $tableColumn['length'] = $length;
        }

        $matches = array();

        $autoincrement = false;
        if (preg_match("/^nextval\('(.*)'(::.*)?\)$/", $tableColumn['default'], $matches)) {
            $tableColumn['sequence'] = $matches[1];
            $tableColumn['default'] = null;
            $autoincrement = true;
        }

        if (stripos($tableColumn['default'], 'NULL') === 0) {
            $tableColumn['default'] = null;
        }

        $length = (isset($tableColumn['length'])) ? $tableColumn['length'] : null;
        if ($length == '-1' && isset($tableColumn['atttypmod'])) {
            $length = $tableColumn['atttypmod'] - 4;
        }
        if ((int) $length <= 0) {
            $length = null;
        }
        $fixed = null;

        if (!isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $precision = null;
        $scale = null;

        if ($this->_platform->hasDoctrineTypeMappingFor($tableColumn['type'])) {
            $dbType = strtolower($tableColumn['type']);
        } else {
            $dbType = strtolower($tableColumn['domain_type']);
            $tableColumn['complete_type'] = $tableColumn['domain_complete_type'];
        }

        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
        $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);

        switch ($dbType) {
            case 'smallint':
            case 'int2':
                $length = null;
                break;
            case 'int':
            case 'int4':
            case 'integer':
                $length = null;
                break;
            case 'bigint':
            case 'int8':
                $length = null;
                break;
            case 'bool':
            case 'boolean':
                $length = null;
                break;
            case 'text':
                $fixed = false;
                break;
            case 'varchar':
            case 'interval':
            case '_varchar':
                $fixed = false;
                break;
            case 'char':
            case 'bpchar':
                $fixed = true;
                break;
            case 'float':
            case 'float4':
            case 'float8':
            case 'double':
            case 'double precision':
            case 'real':
            case 'decimal':
            case 'money':
            case 'numeric':
                if (preg_match('([A-Za-z]+\(([0-9]+)\,([0-9]+)\))', $tableColumn['complete_type'], $match)) {
                    $precision = $match[1];
                    $scale = $match[2];
                    $length = null;
                }
                break;
            case 'year':
                $length = null;
                break;
        }

        $options = array(
            'length'        => $length,
            'notnull'       => (bool) $tableColumn['isnotnull'],
            'default'       => $tableColumn['default'],
            'primary'       => (bool) ($tableColumn['pri'] == 't'),
            'precision'     => $precision,
            'scale'         => $scale,
            'fixed'         => $fixed,
            'unsigned'      => false,
            'autoincrement' => $autoincrement,
            'comment'       => $tableColumn['comment'],
        );

        return new Column($tableColumn['field'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

}
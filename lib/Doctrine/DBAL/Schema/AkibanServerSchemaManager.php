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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;

/**
 * Akiban Server Schema Manager
 *
 * @author Padraig O'Sullivan <osullivan.padraig@gmail.com>
 * @since  2.4
 */
class AkibanServerSchemaManager extends AbstractSchemaManager
{

    /**
     * Get all the existing schema names.
     *
     * @return array
     */
    public function getSchemaNames()
    {
        $rows = $this->_conn->fetchAll("SELECT schema_name FROM information_schema.schemata WHERE schema_name != 'information_schema'");
        return array_map(function($v) { return $v['schema_name']; }, $rows);
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($database = null)
    {
        if (null === $database) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $params["dbname"] = "information_schema";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::dropDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($database = null)
    {
        if (null === $database) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $params["dbname"] = "information_schema";
        $tmpPlatform = $this->_platform;
        $tmpConn = $this->_conn;

        $this->_conn = \Doctrine\DBAL\DriverManager::getConnection($params);
        $this->_platform = $this->_conn->getDatabasePlatform();

        parent::createDatabase($database);

        $this->_platform = $tmpPlatform;
        $this->_conn = $tmpConn;
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
        return $table['table_name'];
    }

    /**
     * @param  array $tableIndexes
     * @param  string $tableName
     * @return array
     */
    protected function _getPortableTableIndexesList($tableIndexes, $tableName=null)
    {
        $indexBuffer = array();
        foreach ($tableIndexes as $tableIndex) {
            if ($tableIndex['index_type'] == "PRIMARY") {
                $keyName = 'primary';
                $buffer['primary'] = true;
                $buffer['non_unique'] = false;
            } else {
                $buffer['primary'] = false;
                $buffer['non_unique'] = ($tableIndex['is_unique'] == 'YES') ? false : true;
            }
            $buffer['key_name'] = $tableIndex['index_name'];
            $buffer['column_name'] = $tableIndex['column_name'];
            $indexBuffer[] = $buffer;
        }
        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['schema_name'];
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return new Sequence($sequence['sequence_name'], $sequence['increment_by'], $sequence['min_value']);
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        if (strtolower($tableColumn['type']) === 'varchar') {
            // get length from varchar definition
            $length = $tableColumn['length'];
        }

        $matches = array();

        $autoincrement = false;
        $tableColumn['default'] = null;

        $length = (isset($tableColumn['length'])) ? $tableColumn['length'] : null;
        if ((int) $length <= 0) {
            $length = null;
        }
        $fixed = null;

        if (!isset($tableColumn['column_name'])) {
            $tableColumn['name'] = '';
        }

        $precision = null;
        $scale = null;

        $dbType = strtolower($tableColumn['type']);
        $type = $this->_platform->getDoctrineTypeMapping($dbType);

        switch ($dbType) {
            case 'smallint':
                $length = null;
                break;
            case 'int':
            case 'integer':
                $length = null;
                break;
            case 'bigint':
                $length = null;
                break;
            case 'boolean':
                $length = null;
                break;
            case 'varchar':
            case 'interval':
                $fixed = false;
                break;
            case 'char':
                $fixed = true;
                break;
            case 'float':
            case 'double':
            case 'double precision':
            case 'real':
            case 'decimal':
            case 'numeric':
                // TODO
                break;
            case 'year':
                $length = null;
                break;
        }

        if ($tableColumn['default'] && preg_match("('([^']+)'::)", $tableColumn['default'], $match)) {
            $tableColumn['default'] = $match[1];
        }

        $notNull = $tableColumn['nullable'] === 'NO';
        $primaryKey = $tableColumn['index_type'] === 'PRIMARY';

        $options = array(
            'length'        => $length,
            'notnull'       => $notNull,
            'default'       => $tableColumn['default'],
            'primary'       => $primaryKey,
            'precision'     => $precision,
            'scale'         => $scale,
            'fixed'         => $fixed,
            'unsigned'      => false,
            'autoincrement' => $autoincrement,
            'comment'       => NULL,
        );

        return new Column($tableColumn['column_name'], Type::getType($type), $options);
    }

}


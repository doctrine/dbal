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
 * SqliteSchemaManager
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @version     $Revision$
 * @since       2.0
 */
class SqliteSchemaManager extends AbstractSchemaManager
{
    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function dropDatabase($database)
    {
        if (file_exists($database)) {
            unlink($database);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function createDatabase($database)
    {
        $params = $this->_conn->getParams();
        $driver = $params['driver'];
        $options = array(
            'driver' => $driver,
            'path' => $database
        );
        $conn = \Doctrine\DBAL\DriverManager::getConnection($options);
        $conn->connect();
        $conn->close();
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['name'];
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

        // fetch primary
        $stmt = $this->_conn->executeQuery( "PRAGMA TABLE_INFO ('$tableName')" );
        $indexArray = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($indexArray as $indexColumnRow) {
            if($indexColumnRow['pk'] == "1") {
                $indexBuffer[] = array(
                    'key_name' => 'primary',
                    'primary' => true,
                    'non_unique' => false,
                    'column_name' => $indexColumnRow['name']
                );
            }
        }

        // fetch regular indexes
        foreach($tableIndexes as $tableIndex) {
            // Ignore indexes with reserved names, e.g. autoindexes
            if (strpos($tableIndex['name'], 'sqlite_') !== 0) {
                $keyName = $tableIndex['name'];
                $idx = array();
                $idx['key_name'] = $keyName;
                $idx['primary'] = false;
                $idx['non_unique'] = $tableIndex['unique']?false:true;

                $stmt = $this->_conn->executeQuery( "PRAGMA INDEX_INFO ( '{$keyName}' )" );
                $indexArray = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ( $indexArray as $indexColumnRow ) {
                    $idx['column_name'] = $indexColumnRow['name'];
                    $indexBuffer[] = $idx;
                }
            }
        }

        return parent::_getPortableTableIndexesList($indexBuffer, $tableName);
    }

    protected function _getPortableTableIndexDefinition($tableIndex)
    {
        return array(
            'name' => $tableIndex['name'],
            'unique' => (bool) $tableIndex['unique']
        );
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $e = explode('(', $tableColumn['type']);
        $tableColumn['type'] = $e[0];
        if (isset($e[1])) {
            $length = trim($e[1], ')');
            $tableColumn['length'] = $length;
        }

        $dbType = strtolower($tableColumn['type']);
        $length = isset($tableColumn['length']) ? $tableColumn['length'] : null;
        $unsigned = (boolean) isset($tableColumn['unsigned']) ? $tableColumn['unsigned'] : false;
        $fixed = false;
        $type = $this->_platform->getDoctrineTypeMapping($dbType);
        $default = $tableColumn['dflt_value'];
        if  ($default == 'NULL') {
            $default = null;
        }
        if ($default !== null) {
            // SQLite returns strings wrapped in single quotes, so we need to strip them
            $default = preg_replace("/^'(.*)'$/", '\1', $default);
        }
        $notnull = (bool) $tableColumn['notnull'];

        if ( ! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $precision = null;
        $scale = null;

        switch ($dbType) {
            case 'char':
                $fixed = true;
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'decimal':
            case 'numeric':
                if (isset($tableColumn['length'])) {
                    if (strpos($tableColumn['length'], ',') === false) {
                        $tableColumn['length'] .= ",0";
                    }                    
                    list($precision, $scale) = array_map('trim', explode(',', $tableColumn['length']));
                }
                $length = null;
                break;
        }

        $options = array(
            'length'   => $length,
            'unsigned' => (bool) $unsigned,
            'fixed'    => $fixed,
            'notnull'  => $notnull,
            'default'  => $default,
            'precision' => $precision,
            'scale'     => $scale,
            'autoincrement' => false,
        );

        return new Column($tableColumn['name'], \Doctrine\DBAL\Types\Type::getType($type), $options);
    }

    protected function _getPortableViewDefinition($view)
    {
        return new View($view['name'], $view['sql']);
    }
}

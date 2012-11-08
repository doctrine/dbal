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

use Doctrine\DBAL\Events;
use Doctrine\DBAL\Event\SchemaColumnDefinitionEventArgs;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Types;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Base class for schema managers. Schema managers are used to inspect and/or
 * modify the database schema/structure.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @since       2.0
 */
abstract class AbstractSchemaManager
{
    /**
     * Holds instance of the Doctrine connection for this schema manager
     *
     * @var \Doctrine\DBAL\Connection
     */
    protected $_conn;

    /**
     * Holds instance of the database platform used for this schema manager
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $_platform;

    /**
     * Constructor. Accepts the Connection instance to manage the schema for
     *
     * @param \Doctrine\DBAL\Connection $conn
     */
    public function __construct(\Doctrine\DBAL\Connection $conn)
    {
        $this->_conn = $conn;
        $this->_platform = $this->_conn->getDatabasePlatform();
    }

    /**
     * Return associated platform.
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_platform;
    }

    /**
     * Try any method on the schema manager. Normally a method throws an
     * exception when your DBMS doesn't support it or if an error occurs.
     * This method allows you to try and method on your SchemaManager
     * instance and will return false if it does not work or is not supported.
     *
     * <code>
     * $result = $sm->tryMethod('dropView', 'view_name');
     * </code>
     *
     * @return mixed
     */
    public function tryMethod()
    {
        $args = func_get_args();
        $method = $args[0];
        unset($args[0]);
        $args = array_values($args);

        try {
            return call_user_func_array(array($this, $method), $args);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List the available databases for this connection
     *
     * @return array $databases
     */
    public function listDatabases()
    {
        $sql = $this->_platform->getListDatabasesSQL();

        $databases = $this->_conn->fetchAll($sql);

        return $this->_getPortableDatabasesList($databases);
    }

    /**
     * List the available sequences for this connection
     *
     * @return Sequence[]
     */
    public function listSequences($database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }
        $sql = $this->_platform->getListSequencesSQL($database);

        $sequences = $this->_conn->fetchAll($sql);

        return $this->filterAssetNames($this->_getPortableSequencesList($sequences));
    }

    /**
     * List the columns for a given table.
     *
     * In contrast to other libraries and to the old version of Doctrine,
     * this column definition does try to contain the 'primary' field for
     * the reason that it is not portable accross different RDBMS. Use
     * {@see listTableIndexes($tableName)} to retrieve the primary key
     * of a table. We're a RDBMS specifies more details these are held
     * in the platformDetails array.
     *
     * @param string $table The name of the table.
     * @param string $database
     * @return Column[]
     */
    public function listTableColumns($table, $database = null)
    {
        if ( ! $database) {
            $database = $this->_conn->getDatabase();
        }

        $sql = $this->_platform->getListTableColumnsSQL($table, $database);

        $tableColumns = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableColumnList($table, $database, $tableColumns);
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

        $tableIndexes = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * Return true if all the given tables exist.
     *
     * @param array $tableNames
     * @return bool
     */
    public function tablesExist($tableNames)
    {
        $tableNames = array_map('strtolower', (array)$tableNames);
        return count($tableNames) == count(\array_intersect($tableNames, array_map('strtolower', $this->listTableNames())));
    }

    /**
     * Return a list of all tables in the current database
     *
     * @return array
     */
    public function listTableNames()
    {
        $sql = $this->_platform->getListTablesSQL();

        $tables = $this->_conn->fetchAll($sql);
        $tableNames = $this->_getPortableTablesList($tables);
        return $this->filterAssetNames($tableNames);
    }

    /**
     * Filter asset names if they are configured to return only a subset of all
     * the found elements.
     *
     * @param array $assetNames
     * @return array
     */
    protected function filterAssetNames($assetNames)
    {
        $filterExpr = $this->getFilterSchemaAssetsExpression();
        if ( ! $filterExpr) {
            return $assetNames;
        }
        return array_values (
            array_filter($assetNames, function ($assetName) use ($filterExpr) {
                $assetName = ($assetName instanceof AbstractAsset) ? $assetName->getName() : $assetName;
                return preg_match($filterExpr, $assetName);
            })
        );
    }

    protected function getFilterSchemaAssetsExpression()
    {
        return $this->_conn->getConfiguration()->getFilterSchemaAssetsExpression();
    }

    /**
     * List the tables for this connection
     *
     * @return Table[]
     */
    public function listTables()
    {
        $tableNames = $this->listTableNames();

        $tables = array();
        foreach ($tableNames as $tableName) {
            $tables[] = $this->listTableDetails($tableName);
        }

        return $tables;
    }

    /**
     * @param  string $tableName
     * @return Table
     */
    public function listTableDetails($tableName)
    {
        $columns = $this->listTableColumns($tableName);
        $foreignKeys = array();
        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeys($tableName);
        }
        $indexes = $this->listTableIndexes($tableName);

        return new Table($tableName, $columns, $indexes, $foreignKeys, false, array());
    }

    /**
     * List the views this connection has
     *
     * @return View[]
     */
    public function listViews()
    {
        $database = $this->_conn->getDatabase();
        $sql = $this->_platform->getListViewsSQL($database);
        $views = $this->_conn->fetchAll($sql);

        return $this->_getPortableViewsList($views);
    }

    /**
     * List the foreign keys for the given table
     *
     * @param string $table  The name of the table
     * @return ForeignKeyConstraint[]
     */
    public function listTableForeignKeys($table, $database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }
        $sql = $this->_platform->getListTableForeignKeysSQL($table, $database);
        $tableForeignKeys = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }

    /* drop*() Methods */

    /**
     * Drops a database.
     *
     * NOTE: You can not drop the database this SchemaManager is currently connected to.
     *
     * @param string $database The name of the database to drop
     */
    public function dropDatabase($database)
    {
        $this->_execSql($this->_platform->getDropDatabaseSQL($database));
    }

    /**
     * Drop the given table
     *
     * @param string $table The name of the table to drop
     */
    public function dropTable($table)
    {
        $this->_execSql($this->_platform->getDropTableSQL($table));
    }

    /**
     * Drop the index from the given table
     *
     * @param Index|string $index  The name of the index
     * @param string|Table $table The name of the table
     */
    public function dropIndex($index, $table)
    {
        if($index instanceof Index) {
            $index = $index->getQuotedName($this->_platform);
        }

        $this->_execSql($this->_platform->getDropIndexSQL($index, $table));
    }

    /**
     * Drop the constraint from the given table
     *
     * @param Constraint $constraint
     * @param string $table   The name of the table
     */
    public function dropConstraint(Constraint $constraint, $table)
    {
        $this->_execSql($this->_platform->getDropConstraintSQL($constraint, $table));
    }

    /**
     * Drops a foreign key from a table.
     *
     * @param ForeignKeyConstraint|string $table The name of the table with the foreign key.
     * @param Table|string $name  The name of the foreign key.
     * @return boolean $result
     */
    public function dropForeignKey($foreignKey, $table)
    {
        $this->_execSql($this->_platform->getDropForeignKeySQL($foreignKey, $table));
    }

    /**
     * Drops a sequence with a given name.
     *
     * @param string $name The name of the sequence to drop.
     */
    public function dropSequence($name)
    {
        $this->_execSql($this->_platform->getDropSequenceSQL($name));
    }

    /**
     * Drop a view
     *
     * @param string $name The name of the view
     * @return boolean $result
     */
    public function dropView($name)
    {
        $this->_execSql($this->_platform->getDropViewSQL($name));
    }

    /* create*() Methods */

    /**
     * Creates a new database.
     *
     * @param string $database The name of the database to create.
     */
    public function createDatabase($database)
    {
        $this->_execSql($this->_platform->getCreateDatabaseSQL($database));
    }

    /**
     * Create a new table.
     *
     * @param Table $table
     * @param int $createFlags
     */
    public function createTable(Table $table)
    {
        $createFlags = AbstractPlatform::CREATE_INDEXES|AbstractPlatform::CREATE_FOREIGNKEYS;
        $this->_execSql($this->_platform->getCreateTableSQL($table, $createFlags));
    }

    /**
     * Create a new sequence
     *
     * @param Sequence $sequence
     * @throws \Doctrine\DBAL\ConnectionException     if something fails at database level
     */
    public function createSequence($sequence)
    {
        $this->_execSql($this->_platform->getCreateSequenceSQL($sequence));
    }

    /**
     * Create a constraint on a table
     *
     * @param Constraint $constraint
     * @param string|Table $table
     */
    public function createConstraint(Constraint $constraint, $table)
    {
        $this->_execSql($this->_platform->getCreateConstraintSQL($constraint, $table));
    }

    /**
     * Create a new index on a table
     *
     * @param Index     $index
     * @param string    $table         name of the table on which the index is to be created
     */
    public function createIndex(Index $index, $table)
    {
        $this->_execSql($this->_platform->getCreateIndexSQL($index, $table));
    }

    /**
     * Create a new foreign key
     *
     * @param ForeignKeyConstraint  $foreignKey    ForeignKey instance
     * @param string|Table          $table         name of the table on which the foreign key is to be created
     */
    public function createForeignKey(ForeignKeyConstraint $foreignKey, $table)
    {
        $this->_execSql($this->_platform->getCreateForeignKeySQL($foreignKey, $table));
    }

    /**
     * Create a new view
     *
     * @param View $view
     */
    public function createView(View $view)
    {
        $this->_execSql($this->_platform->getCreateViewSQL($view->getQuotedName($this->_platform), $view->getSql()));
    }

    /* dropAndCreate*() Methods */

    /**
     * Drop and create a constraint
     *
     * @param Constraint    $constraint
     * @param string        $table
     * @see dropConstraint()
     * @see createConstraint()
     */
    public function dropAndCreateConstraint(Constraint $constraint, $table)
    {
        $this->tryMethod('dropConstraint', $constraint, $table);
        $this->createConstraint($constraint, $table);
    }

    /**
     * Drop and create a new index on a table
     *
     * @param string|Table $table         name of the table on which the index is to be created
     * @param Index $index
     */
    public function dropAndCreateIndex(Index $index, $table)
    {
        $this->tryMethod('dropIndex', $index->getQuotedName($this->_platform), $table);
        $this->createIndex($index, $table);
    }

    /**
     * Drop and create a new foreign key
     *
     * @param ForeignKeyConstraint  $foreignKey    associative array that defines properties of the foreign key to be created.
     * @param string|Table          $table         name of the table on which the foreign key is to be created
     */
    public function dropAndCreateForeignKey(ForeignKeyConstraint $foreignKey, $table)
    {
        $this->tryMethod('dropForeignKey', $foreignKey, $table);
        $this->createForeignKey($foreignKey, $table);
    }

    /**
     * Drop and create a new sequence
     *
     * @param Sequence $sequence
     * @throws \Doctrine\DBAL\ConnectionException     if something fails at database level
     */
    public function dropAndCreateSequence(Sequence $sequence)
    {
        $this->tryMethod('dropSequence', $sequence->getQuotedName($this->_platform));
        $this->createSequence($sequence);
    }

    /**
     * Drop and create a new table.
     *
     * @param Table $table
     */
    public function dropAndCreateTable(Table $table)
    {
        $this->tryMethod('dropTable', $table->getQuotedName($this->_platform));
        $this->createTable($table);
    }

    /**
     * Drop and creates a new database.
     *
     * @param string $database The name of the database to create.
     */
    public function dropAndCreateDatabase($database)
    {
        $this->tryMethod('dropDatabase', $database);
        $this->createDatabase($database);
    }

    /**
     * Drop and create a new view
     *
     * @param View $view
     */
    public function dropAndCreateView(View $view)
    {
        $this->tryMethod('dropView', $view->getQuotedName($this->_platform));
        $this->createView($view);
    }

    /* alterTable() Methods */

    /**
     * Alter an existing tables schema
     *
     * @param TableDiff $tableDiff
     */
    public function alterTable(TableDiff $tableDiff)
    {
        $queries = $this->_platform->getAlterTableSQL($tableDiff);
        if (is_array($queries) && count($queries)) {
            foreach ($queries as $ddlQuery) {
                $this->_execSql($ddlQuery);
            }
        }
    }

    /**
     * Rename a given table to another name
     *
     * @param string $name     The current name of the table
     * @param string $newName  The new name of the table
     */
    public function renameTable($name, $newName)
    {
        $tableDiff = new TableDiff($name);
        $tableDiff->newName = $newName;
        $this->alterTable($tableDiff);
    }

    /**
     * Methods for filtering return values of list*() methods to convert
     * the native DBMS data definition to a portable Doctrine definition
     */

    protected function _getPortableDatabasesList($databases)
    {
        $list = array();
        foreach ($databases as $value) {
            if ($value = $this->_getPortableDatabaseDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database;
    }

    protected function _getPortableFunctionsList($functions)
    {
        $list = array();
        foreach ($functions as $value) {
            if ($value = $this->_getPortableFunctionDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableFunctionDefinition($function)
    {
        return $function;
    }

    protected function _getPortableTriggersList($triggers)
    {
        $list = array();
        foreach ($triggers as $value) {
            if ($value = $this->_getPortableTriggerDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableTriggerDefinition($trigger)
    {
        return $trigger;
    }

    protected function _getPortableSequencesList($sequences)
    {
        $list = array();
        foreach ($sequences as $value) {
            if ($value = $this->_getPortableSequenceDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    /**
     * @param array $sequence
     * @return Sequence
     */
    protected function _getPortableSequenceDefinition($sequence)
    {
        throw DBALException::notSupported('Sequences');
    }

    /**
     * Independent of the database the keys of the column list result are lowercased.
     *
     * The name of the created column instance however is kept in its case.
     *
     * @param  string $table The name of the table.
     * @param  string $database
     * @param  array  $tableColumns
     * @return array
     */
    protected function _getPortableTableColumnList($table, $database, $tableColumns)
    {
        $eventManager = $this->_platform->getEventManager();

        $list = array();
        foreach ($tableColumns as $tableColumn) {
            $column = null;
            $defaultPrevented = false;

            if (null !== $eventManager && $eventManager->hasListeners(Events::onSchemaColumnDefinition)) {
                $eventArgs = new SchemaColumnDefinitionEventArgs($tableColumn, $table, $database, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaColumnDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $column = $eventArgs->getColumn();
            }

            if ( ! $defaultPrevented) {
                $column = $this->_getPortableTableColumnDefinition($tableColumn);
            }

            if ($column) {
                $name = strtolower($column->getQuotedName($this->_platform));
                $list[$name] = $column;
            }
        }
        return $list;
    }

    /**
     * Get Table Column Definition
     *
     * @param array $tableColumn
     * @return Column
     */
    abstract protected function _getPortableTableColumnDefinition($tableColumn);

    /**
     * Aggregate and group the index results according to the required data result.
     *
     * @param  array $tableIndexRows
     * @param  string $tableName
     * @return array
     */
    protected function _getPortableTableIndexesList($tableIndexRows, $tableName=null)
    {
        $result = array();
        foreach($tableIndexRows as $tableIndex) {
            $indexName = $keyName = $tableIndex['key_name'];
            if($tableIndex['primary']) {
                $keyName = 'primary';
            }
            $keyName = strtolower($keyName);

            if(!isset($result[$keyName])) {
                $result[$keyName] = array(
                    'name' => $indexName,
                    'columns' => array($tableIndex['column_name']),
                    'unique' => $tableIndex['non_unique'] ? false : true,
                    'primary' => $tableIndex['primary'],
                    'flags' => isset($tableIndex['flags']) ? $tableIndex['flags'] : array(),
                );
            } else {
                $result[$keyName]['columns'][] = $tableIndex['column_name'];
            }
        }

        $eventManager = $this->_platform->getEventManager();

        $indexes = array();
        foreach($result as $indexKey => $data) {
            $index = null;
            $defaultPrevented = false;

            if (null !== $eventManager && $eventManager->hasListeners(Events::onSchemaIndexDefinition)) {
                $eventArgs = new SchemaIndexDefinitionEventArgs($data, $tableName, $this->_conn);
                $eventManager->dispatchEvent(Events::onSchemaIndexDefinition, $eventArgs);

                $defaultPrevented = $eventArgs->isDefaultPrevented();
                $index = $eventArgs->getIndex();
            }

            if ( ! $defaultPrevented) {
                $index = new Index($data['name'], $data['columns'], $data['unique'], $data['primary'], $data['flags']);
            }

            if ($index) {
                $indexes[$indexKey] = $index;
            }
        }

        return $indexes;
    }

    protected function _getPortableTablesList($tables)
    {
        $list = array();
        foreach ($tables as $value) {
            if ($value = $this->_getPortableTableDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table;
    }

    protected function _getPortableUsersList($users)
    {
        $list = array();
        foreach ($users as $value) {
            if ($value = $this->_getPortableUserDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableUserDefinition($user)
    {
        return $user;
    }

    protected function _getPortableViewsList($views)
    {
        $list = array();
        foreach ($views as $value) {
            if ($view = $this->_getPortableViewDefinition($value)) {
                $viewName = strtolower($view->getQuotedName($this->_platform));
                $list[$viewName] = $view;
            }
        }
        return $list;
    }

    protected function _getPortableViewDefinition($view)
    {
        return false;
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys)
    {
        $list = array();
        foreach ($tableForeignKeys as $value) {
            if ($value = $this->_getPortableTableForeignKeyDefinition($value)) {
                $list[] = $value;
            }
        }
        return $list;
    }

    protected function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        return $tableForeignKey;
    }

    protected function _execSql($sql)
    {
        foreach ((array) $sql as $query) {
            $this->_conn->executeUpdate($query);
        }
    }

    /**
     * Create a schema instance for the current database.
     *
     * @return Schema
     */
    public function createSchema()
    {
        $sequences = array();
        if($this->_platform->supportsSequences()) {
            $sequences = $this->listSequences();
        }
        $tables = $this->listTables();

        return new Schema($tables, $sequences, $this->createSchemaConfig());
    }

    /**
     * Create the configuration for this schema.
     *
     * @return SchemaConfig
     */
    public function createSchemaConfig()
    {
        $schemaConfig = new SchemaConfig();
        $schemaConfig->setMaxIdentifierLength($this->_platform->getMaxIdentifierLength());

        $searchPaths = $this->getSchemaSearchPaths();
        if (isset($searchPaths[0])) {
            $schemaConfig->setName($searchPaths[0]);
        }

        $params = $this->_conn->getParams();
        if (isset($params['defaultTableOptions'])) {
            $schemaConfig->setDefaultTableOptions($params['defaultTableOptions']);
        }

        return $schemaConfig;
    }

    /**
     * The search path for namespaces in the currently connected database.
     *
     * The first entry is usually the default namespace in the Schema. All
     * further namespaces contain tables/sequences which can also be addressed
     * with a short, not full-qualified name.
     *
     * For databases that don't support subschema/namespaces this method
     * returns the name of the currently connected database.
     *
     * @return array
     */
    public function getSchemaSearchPaths()
    {
        return array($this->_conn->getDatabase());
    }

    /**
     * Given a table comment this method tries to extract a typehint for Doctrine Type, or returns
     * the type given as default.
     *
     * @param  string $comment
     * @param  string $currentType
     * @return string
     */
    public function extractDoctrineTypeFromComment($comment, $currentType)
    {
        if (preg_match("(\(DC2Type:([a-zA-Z0-9_]+)\))", $comment, $match)) {
            $currentType = $match[1];
        }
        return $currentType;
    }

    public function removeDoctrineTypeFromComment($comment, $type)
    {
        return str_replace('(DC2Type:'.$type.')', '', $comment);
    }
}

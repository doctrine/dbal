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

namespace Doctrine\DBAL;

use PDO, Closure, Exception,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Driver\Connection as DriverConnection,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Cache\ResultCacheStatement,
    Doctrine\DBAL\Cache\QueryCacheProfile,
    Doctrine\DBAL\Cache\ArrayStatement,
    Doctrine\DBAL\Cache\CacheException;

/**
 * A wrapper around a Doctrine\DBAL\Driver\Connection that adds features like
 * events, transaction isolation levels, configuration, emulated transaction nesting,
 * lazy connecting and more.
 *
 * 
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author  Lukas Smith <smith@pooteeweet.org> (MDB2 library)
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class Connection implements DriverConnection
{
    /**
     * Constant for transaction isolation level READ UNCOMMITTED.
     */
    const TRANSACTION_READ_UNCOMMITTED = 1;

    /**
     * Constant for transaction isolation level READ COMMITTED.
     */
    const TRANSACTION_READ_COMMITTED = 2;

    /**
     * Constant for transaction isolation level REPEATABLE READ.
     */
    const TRANSACTION_REPEATABLE_READ = 3;

    /**
     * Constant for transaction isolation level SERIALIZABLE.
     */
    const TRANSACTION_SERIALIZABLE = 4;

    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     *
     * @var int
     */
    const PARAM_INT_ARRAY = 101;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     *
     * @var int
     */
    const PARAM_STR_ARRAY = 102;

    /**
     * Offset by which PARAM_* constants are detected as arrays of the param type.
     *
     * @var int
     */
    const ARRAY_PARAM_OFFSET = 100;

    /**
     * The wrapped driver connection.
     *
     * @var \Doctrine\DBAL\Driver\Connection
     */
    protected $_conn;

    /**
     * @var \Doctrine\DBAL\Configuration
     */
    protected $_config;

    /**
     * @var \Doctrine\Common\EventManager
     */
    protected $_eventManager;

    /**
     * @var \Doctrine\DBAL\Query\Expression\ExpressionBuilder
     */
    protected $_expr;

    /**
     * Whether or not a connection has been established.
     *
     * @var boolean
     */
    private $_isConnected = false;

    /**
     * The transaction nesting level.
     *
     * @var integer
     */
    private $_transactionNestingLevel = 0;

    /**
     * The currently active transaction isolation level.
     *
     * @var integer
     */
    private $_transactionIsolationLevel;

    /**
     * If nested transactions should use savepoints
     *
     * @var integer
     */
    private $_nestTransactionsWithSavepoints;

    /**
     * The parameters used during creation of the Connection instance.
     *
     * @var array
     */
    private $_params = array();

    /**
     * The DatabasePlatform object that provides information about the
     * database platform used by the connection.
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    protected $_platform;

    /**
     * The schema manager.
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    protected $_schemaManager;

    /**
     * The used DBAL driver.
     *
     * @var \Doctrine\DBAL\Driver
     */
    protected $_driver;

    /**
     * Flag that indicates whether the current transaction is marked for rollback only.
     *
     * @var boolean
     */
    private $_isRollbackOnly = false;

    private $_defaultFetchMode = PDO::FETCH_ASSOC;

    /**
     * Initializes a new instance of the Connection class.
     *
     * @param array $params  The connection parameters.
     * @param Driver $driver
     * @param Configuration $config
     * @param EventManager $eventManager
     */
    public function __construct(array $params, Driver $driver, Configuration $config = null,
            EventManager $eventManager = null)
    {
        $this->_driver = $driver;
        $this->_params = $params;

        if (isset($params['pdo'])) {
            $this->_conn = $params['pdo'];
            $this->_isConnected = true;
        }

        // Create default config and event manager if none given
        if ( ! $config) {
            $config = new Configuration();
        }

        if ( ! $eventManager) {
            $eventManager = new EventManager();
        }

        $this->_config = $config;
        $this->_eventManager = $eventManager;

        $this->_expr = new Query\Expression\ExpressionBuilder($this);

        if ( ! isset($params['platform'])) {
            $this->_platform = $driver->getDatabasePlatform();
        } else if ($params['platform'] instanceof Platforms\AbstractPlatform) {
            $this->_platform = $params['platform'];
        } else {
            throw DBALException::invalidPlatformSpecified();
        }

        $this->_platform->setEventManager($eventManager);

        $this->_transactionIsolationLevel = $this->_platform->getDefaultTransactionIsolationLevel();
    }

    /**
     * Gets the parameters used during instantiation.
     *
     * @return array $params
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Gets the name of the database this Connection is connected to.
     *
     * @return string $database
     */
    public function getDatabase()
    {
        return $this->_driver->getDatabase($this);
    }

    /**
     * Gets the hostname of the currently connected database.
     *
     * @return string
     */
    public function getHost()
    {
        return isset($this->_params['host']) ? $this->_params['host'] : null;
    }

    /**
     * Gets the port of the currently connected database.
     *
     * @return mixed
     */
    public function getPort()
    {
        return isset($this->_params['port']) ? $this->_params['port'] : null;
    }

    /**
     * Gets the username used by this connection.
     *
     * @return string
     */
    public function getUsername()
    {
        return isset($this->_params['user']) ? $this->_params['user'] : null;
    }

    /**
     * Gets the password used by this connection.
     *
     * @return string
     */
    public function getPassword()
    {
        return isset($this->_params['password']) ? $this->_params['password'] : null;
    }

    /**
     * Gets the DBAL driver instance.
     *
     * @return \Doctrine\DBAL\Driver
     */
    public function getDriver()
    {
        return $this->_driver;
    }

    /**
     * Gets the Configuration used by the Connection.
     *
     * @return \Doctrine\DBAL\Configuration
     */
    public function getConfiguration()
    {
        return $this->_config;
    }

    /**
     * Gets the EventManager used by the Connection.
     *
     * @return \Doctrine\Common\EventManager
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }

    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDatabasePlatform()
    {
        return $this->_platform;
    }

    /**
     * Gets the ExpressionBuilder for the connection.
     *
     * @return \Doctrine\DBAL\Query\Expression\ExpressionBuilder
     */
    public function getExpressionBuilder()
    {
        return $this->_expr;
    }

    /**
     * Establishes the connection with the database.
     *
     * @return boolean TRUE if the connection was successfully established, FALSE if
     *                 the connection is already open.
     */
    public function connect()
    {
        if ($this->_isConnected) return false;

        $driverOptions = isset($this->_params['driverOptions']) ?
                $this->_params['driverOptions'] : array();
        $user = isset($this->_params['user']) ? $this->_params['user'] : null;
        $password = isset($this->_params['password']) ?
                $this->_params['password'] : null;

        $this->_conn = $this->_driver->connect($this->_params, $user, $password, $driverOptions);
        $this->_isConnected = true;

        if ($this->_eventManager->hasListeners(Events::postConnect)) {
            $eventArgs = new Event\ConnectionEventArgs($this);
            $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
        }

        return true;
    }

    /**
     * setFetchMode
     *
     * @param integer $fetchMode
     */
    public function setFetchMode($fetchMode)
    {
        $this->_defaultFetchMode = $fetchMode;
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @param string $statement The SQL query.
     * @param array $params The query parameters.
     * @return array
     */
    public function fetchAssoc($statement, array $params = array())
    {
        return $this->executeQuery($statement, $params)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @param string $statement         sql query to be executed
     * @param array $params             prepared statement params
     * @return array
     */
    public function fetchArray($statement, array $params = array())
    {
        return $this->executeQuery($statement, $params)->fetch(PDO::FETCH_NUM);
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string $statement         sql query to be executed
     * @param array $params             prepared statement params
     * @param int $colnum               0-indexed column number to retrieve
     * @return mixed
     */
    public function fetchColumn($statement, array $params = array(), $colnum = 0)
    {
        return $this->executeQuery($statement, $params)->fetchColumn($colnum);
    }

    /**
     * Whether an actual connection to the database is established.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->_isConnected;
    }

    /**
     * Checks whether a transaction is currently active.
     *
     * @return boolean TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive()
    {
        return $this->_transactionNestingLevel > 0;
    }

    /**
     * Executes an SQL DELETE statement on a table.
     *
     * @param string $tableName The name of the table on which to delete.
     * @param array $identifier The deletion criteria. An associative array containing column-value pairs.
     * @param array $types The types of identifiers
     * @return integer The number of affected rows.
     */
    public function delete($tableName, array $identifier, array $types = array())
    {
        $this->connect();

        $criteria = array();

        foreach (array_keys($identifier) as $columnName) {
            $criteria[] = $columnName . ' = ?';
        }

        if ( ! is_int(key($types))) {
            $types = $this->extractTypeValues($identifier, $types);
        }

        $query = 'DELETE FROM ' . $tableName . ' WHERE ' . implode(' AND ', $criteria);

        return $this->executeUpdate($query, array_values($identifier), $types);
    }

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close()
    {
        unset($this->_conn);

        $this->_isConnected = false;
    }

    /**
     * Sets the transaction isolation level.
     *
     * @param integer $level The level to set.
     * @return integer
     */
    public function setTransactionIsolation($level)
    {
        $this->_transactionIsolationLevel = $level;

        return $this->executeUpdate($this->_platform->getSetTransactionIsolationSQL($level));
    }

    /**
     * Gets the currently active transaction isolation level.
     *
     * @return integer The current transaction isolation level.
     */
    public function getTransactionIsolation()
    {
        return $this->_transactionIsolationLevel;
    }

    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * @param string $tableName The name of the table to update.
     * @param array $data An associative array containing column-value pairs.
     * @param array $identifier The update criteria. An associative array containing column-value pairs.
     * @param array $types Types of the merged $data and $identifier arrays in that order.
     * @return integer The number of affected rows.
     */
    public function update($tableName, array $data, array $identifier, array $types = array())
    {
        $this->connect();
        $set = array();

        foreach ($data as $columnName => $value) {
            $set[] = $columnName . ' = ?';
        }

        if ( ! is_int(key($types))) {
            $types = $this->extractTypeValues(array_merge($data, $identifier), $types);
        }

        $params = array_merge(array_values($data), array_values($identifier));

        $sql  = 'UPDATE ' . $tableName . ' SET ' . implode(', ', $set)
                . ' WHERE ' . implode(' = ? AND ', array_keys($identifier))
                . ' = ?';

        return $this->executeUpdate($sql, $params, $types);
    }

    /**
     * Inserts a table row with specified data.
     *
     * @param string $tableName The name of the table to insert data into.
     * @param array $data An associative array containing column-value pairs.
     * @param array $types Types of the inserted data.
     * @return integer The number of affected rows.
     */
    public function insert($tableName, array $data, array $types = array())
    {
        $this->connect();

        if ( ! is_int(key($types))) {
            $types = $this->extractTypeValues($data, $types);
        }

        $query = 'INSERT INTO ' . $tableName
               . ' (' . implode(', ', array_keys($data)) . ')'
               . ' VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')';

        return $this->executeUpdate($query, array_values($data), $types);
    }

    /**
     * Extract ordered type list from two associate key lists of data and types.
     *
     * @param array $data
     * @param array $types
     *
     * @return array
     */
    private function extractTypeValues(array $data, array $types)
    {
        $typeValues = array();

        foreach ($data as $k => $_) {
            $typeValues[] = isset($types[$k])
                ? $types[$k]
                : \PDO::PARAM_STR;
        }

        return $typeValues;
    }

    /**
     * Quote a string so it can be safely used as a table or column name, even if
     * it is a reserved name.
     *
     * Delimiting style depends on the underlying database platform that is being used.
     *
     * NOTE: Just because you CAN use quoted identifiers does not mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @param string $str The name to be quoted.
     * @return string The quoted name.
     */
    public function quoteIdentifier($str)
    {
        return $this->_platform->quoteIdentifier($str);
    }

    /**
     * Quotes a given input parameter.
     *
     * @param mixed $input Parameter to be quoted.
     * @param string $type Type of the parameter.
     * @return string The quoted parameter.
     */
    public function quote($input, $type = null)
    {
        $this->connect();

        list($value, $bindingType) = $this->getBindingInfo($input, $type);
        return $this->_conn->quote($value, $bindingType);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array.
     *
     * @param string $sql The SQL query.
     * @param array $params The query parameters.
     * @param array $types Query parameter types.
     * @return array
     */
    public function fetchAll($sql, array $params = array(), $types = array())
    {
        return $this->executeQuery($sql, $params, $types)->fetchAll();
    }

    /**
     * Prepares an SQL statement.
     *
     * @param string $statement The SQL statement to prepare.
     * @return \Doctrine\DBAL\Driver\Statement The prepared statement.
     */
    public function prepare($statement)
    {
        $this->connect();

        try {
            $stmt = new Statement($statement, $this);
        } catch (\Exception $ex) {
            throw DBALException::driverExceptionDuringQuery($ex, $statement);
        }

        $stmt->setFetchMode($this->_defaultFetchMode);

        return $stmt;
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string $query The SQL query to execute.
     * @param array $params The parameters to bind to the query, if any.
     * @param array $types The types the previous parameters are in.
     * @param QueryCacheProfile $qcp
     * @return \Doctrine\DBAL\Driver\Statement The executed statement.
     * @internal PERF: Directly prepares a driver statement, not a wrapper.
     */
    public function executeQuery($query, array $params = array(), $types = array(), QueryCacheProfile $qcp = null)
    {
        if ($qcp !== null) {
            return $this->executeCacheQuery($query, $params, $types, $qcp);
        }

        $this->connect();

        $logger = $this->_config->getSQLLogger();
        if ($logger) {
            $logger->startQuery($query, $params, $types);
        }

        try {
            if ($params) {
                list($query, $params, $types) = SQLParserUtils::expandListParameters($query, $params, $types);

                $stmt = $this->_conn->prepare($query);
                if ($types) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $stmt->execute();
                } else {
                    $stmt->execute($params);
                }
            } else {
                $stmt = $this->_conn->query($query);
            }
        } catch (\Exception $ex) {
            throw DBALException::driverExceptionDuringQuery($ex, $query, $this->resolveParams($params, $types));
        }

        $stmt->setFetchMode($this->_defaultFetchMode);

        if ($logger) {
            $logger->stopQuery();
        }

        return $stmt;
    }

    /**
     * Execute a caching query and
     *
     * @param string $query
     * @param array $params
     * @param array $types
     * @param QueryCacheProfile $qcp
     * @return \Doctrine\DBAL\Driver\ResultStatement
     */
    public function executeCacheQuery($query, $params, $types, QueryCacheProfile $qcp)
    {
        $resultCache = $qcp->getResultCacheDriver() ?: $this->_config->getResultCacheImpl();
        if ( ! $resultCache) {
            throw CacheException::noResultDriverConfigured();
        }

        list($cacheKey, $realKey) = $qcp->generateCacheKeys($query, $params, $types);

        // fetch the row pointers entry
        if ($data = $resultCache->fetch($cacheKey)) {
            // is the real key part of this row pointers map or is the cache only pointing to other cache keys?
            if (isset($data[$realKey])) {
                $stmt = new ArrayStatement($data[$realKey]);
            } else if (array_key_exists($realKey, $data)) {
                $stmt = new ArrayStatement(array());
            }
        }

        if (!isset($stmt)) {
            $stmt = new ResultCacheStatement($this->executeQuery($query, $params, $types), $resultCache, $cacheKey, $realKey, $qcp->getLifetime());
        }

        $stmt->setFetchMode($this->_defaultFetchMode);

        return $stmt;
    }

    /**
     * Executes an, optionally parametrized, SQL query and returns the result,
     * applying a given projection/transformation function on each row of the result.
     *
     * @param string $query The SQL query to execute.
     * @param array $params The parameters, if any.
     * @param Closure $mapper The transformation function that is applied on each row.
     *                        The function receives a single parameter, an array, that
     *                        represents a row of the result set.
     * @return mixed The projected result of the query.
     */
    public function project($query, array $params, Closure $function)
    {
        $result = array();
        $stmt = $this->executeQuery($query, $params ?: array());

        while ($row = $stmt->fetch()) {
            $result[] = $function($row);
        }

        $stmt->closeCursor();

        return $result;
    }

    /**
     * Executes an SQL statement, returning a result set as a Statement object.
     *
     * @param string $statement
     * @param integer $fetchType
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function query()
    {
        $this->connect();

        $args = func_get_args();

        $logger = $this->_config->getSQLLogger();
        if ($logger) {
            $logger->startQuery($args[0]);
        }

        try {
            switch (func_num_args()) {
                case 1:
                    $statement = $this->_conn->query($args[0]);
                    break;
                case 2:
                    $statement = $this->_conn->query($args[0], $args[1]);
                    break;
                default:
                    $statement = call_user_func_array(array($this->_conn, 'query'), $args);
                    break;
            }
        } catch (\Exception $ex) {
            throw DBALException::driverExceptionDuringQuery($ex, $args[0]);
        }

        $statement->setFetchMode($this->_defaultFetchMode);

        if ($logger) {
            $logger->stopQuery();
        }

        return $statement;
    }

    /**
     * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
     * and returns the number of affected rows.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string $query The SQL query.
     * @param array $params The query parameters.
     * @param array $types The parameter types.
     * @return integer The number of affected rows.
     * @internal PERF: Directly prepares a driver statement, not a wrapper.
     */
    public function executeUpdate($query, array $params = array(), array $types = array())
    {
        $this->connect();

        $logger = $this->_config->getSQLLogger();
        if ($logger) {
            $logger->startQuery($query, $params, $types);
        }

        try {
            if ($params) {
                list($query, $params, $types) = SQLParserUtils::expandListParameters($query, $params, $types);

                $stmt = $this->_conn->prepare($query);
                if ($types) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $stmt->execute();
                } else {
                    $stmt->execute($params);
                }
                $result = $stmt->rowCount();
            } else {
                $result = $this->_conn->exec($query);
            }
        } catch (\Exception $ex) {
            throw DBALException::driverExceptionDuringQuery($ex, $query, $this->resolveParams($params, $types));
        }

        if ($logger) {
            $logger->stopQuery();
        }

        return $result;
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     *
     * @param string $statement
     * @return integer The number of affected rows.
     */
    public function exec($statement)
    {
        $this->connect();

        $logger = $this->_config->getSQLLogger();
        if ($logger) {
            $logger->startQuery($statement);
        }

        try {
            $result = $this->_conn->exec($statement);
        } catch (\Exception $ex) {
            throw DBALException::driverExceptionDuringQuery($ex, $statement);
        }

        if ($logger) {
            $logger->stopQuery();
        }

        return $result;
    }

    /**
     * Returns the current transaction nesting level.
     *
     * @return integer The nesting level. A value of 0 means there's no active transaction.
     */
    public function getTransactionNestingLevel()
    {
        return $this->_transactionNestingLevel;
    }

    /**
     * Fetch the SQLSTATE associated with the last database operation.
     *
     * @return integer The last error code.
     */
    public function errorCode()
    {
        $this->connect();
        return $this->_conn->errorCode();
    }

    /**
     * Fetch extended error information associated with the last database operation.
     *
     * @return array The last error information.
     */
    public function errorInfo()
    {
        $this->connect();
        return $this->_conn->errorInfo();
    }

    /**
     * Returns the ID of the last inserted row, or the last value from a sequence object,
     * depending on the underlying driver.
     *
     * Note: This method may not return a meaningful or consistent result across different drivers,
     * because the underlying database may not even support the notion of AUTO_INCREMENT/IDENTITY
     * columns or sequences.
     *
     * @param string $seqName Name of the sequence object from which the ID should be returned.
     * @return string A string representation of the last inserted ID.
     */
    public function lastInsertId($seqName = null)
    {
        $this->connect();
        return $this->_conn->lastInsertId($seqName);
    }

    /**
     * Executes a function in a transaction.
     *
     * The function gets passed this Connection instance as an (optional) parameter.
     *
     * If an exception occurs during execution of the function or transaction commit,
     * the transaction is rolled back and the exception re-thrown.
     *
     * @param Closure $func The function to execute transactionally.
     */
    public function transactional(Closure $func)
    {
        $this->beginTransaction();
        try {
            $func($this);
            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Set if nested transactions should use savepoints
     *
     * @param boolean $nestTransactionsWithSavepoints
     * @return void
     */
    public function setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints)
    {
        if ($this->_transactionNestingLevel > 0) {
            throw ConnectionException::mayNotAlterNestedTransactionWithSavepointsInTransaction();
        }

        if ( ! $this->_platform->supportsSavepoints()) {
            throw ConnectionException::savepointsNotSupported();
        }

        $this->_nestTransactionsWithSavepoints = $nestTransactionsWithSavepoints;
    }

    /**
     * Get if nested transactions should use savepoints
     *
     * @return boolean
     */
    public function getNestTransactionsWithSavepoints()
    {
        return $this->_nestTransactionsWithSavepoints;
    }

    /**
     * Returns the savepoint name to use for nested transactions are false if they are not supported
     * "savepointFormat" parameter is not set
     *
     * @return mixed a string with the savepoint name or false
     */
    protected function _getNestedTransactionSavePointName()
    {
        return 'DOCTRINE2_SAVEPOINT_'.$this->_transactionNestingLevel;
    }

    /**
     * Starts a transaction by suspending auto-commit mode.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->connect();

        ++$this->_transactionNestingLevel;

        $logger = $this->_config->getSQLLogger();

        if ($this->_transactionNestingLevel == 1) {
            if ($logger) {
                $logger->startQuery('"START TRANSACTION"');
            }
            $this->_conn->beginTransaction();
            if ($logger) {
                $logger->stopQuery();
            }
        } else if ($this->_nestTransactionsWithSavepoints) {
            if ($logger) {
                $logger->startQuery('"SAVEPOINT"');
            }
            $this->createSavepoint($this->_getNestedTransactionSavePointName());
            if ($logger) {
                $logger->stopQuery();
            }
        }
    }

    /**
     * Commits the current transaction.
     *
     * @return void
     * @throws ConnectionException If the commit failed due to no active transaction or
     *                             because the transaction was marked for rollback only.
     */
    public function commit()
    {
        if ($this->_transactionNestingLevel == 0) {
            throw ConnectionException::noActiveTransaction();
        }
        if ($this->_isRollbackOnly) {
            throw ConnectionException::commitFailedRollbackOnly();
        }

        $this->connect();

        $logger = $this->_config->getSQLLogger();

        if ($this->_transactionNestingLevel == 1) {
            if ($logger) {
                $logger->startQuery('"COMMIT"');
            }
            $this->_conn->commit();
            if ($logger) {
                $logger->stopQuery();
            }
        } else if ($this->_nestTransactionsWithSavepoints) {
            if ($logger) {
                $logger->startQuery('"RELEASE SAVEPOINT"');
            }
            $this->releaseSavepoint($this->_getNestedTransactionSavePointName());
            if ($logger) {
                $logger->stopQuery();
            }
        }

        --$this->_transactionNestingLevel;
    }

    /**
     * Cancel any database changes done during the current transaction.
     *
     * this method can be listened with onPreTransactionRollback and onTransactionRollback
     * eventlistener methods
     *
     * @throws ConnectionException If the rollback operation failed.
     */
    public function rollBack()
    {
        if ($this->_transactionNestingLevel == 0) {
            throw ConnectionException::noActiveTransaction();
        }

        $this->connect();

        $logger = $this->_config->getSQLLogger();

        if ($this->_transactionNestingLevel == 1) {
            if ($logger) {
                $logger->startQuery('"ROLLBACK"');
            }
            $this->_transactionNestingLevel = 0;
            $this->_conn->rollback();
            $this->_isRollbackOnly = false;
            if ($logger) {
                $logger->stopQuery();
            }
        } else if ($this->_nestTransactionsWithSavepoints) {
            if ($logger) {
                $logger->startQuery('"ROLLBACK TO SAVEPOINT"');
            }
            $this->rollbackSavepoint($this->_getNestedTransactionSavePointName());
            --$this->_transactionNestingLevel;
            if ($logger) {
                $logger->stopQuery();
            }
        } else {
            $this->_isRollbackOnly = true;
            --$this->_transactionNestingLevel;
        }
    }

    /**
     * createSavepoint
     * creates a new savepoint
     *
     * @param string $savepoint     name of a savepoint to set
     * @return void
     */
    public function createSavepoint($savepoint)
    {
        if ( ! $this->_platform->supportsSavepoints()) {
            throw ConnectionException::savepointsNotSupported();
        }

        $this->_conn->exec($this->_platform->createSavePoint($savepoint));
    }

    /**
     * releaseSavePoint
     * releases given savepoint
     *
     * @param string $savepoint     name of a savepoint to release
     * @return void
     */
    public function releaseSavepoint($savepoint)
    {
        if ( ! $this->_platform->supportsSavepoints()) {
            throw ConnectionException::savepointsNotSupported();
        }

        if ($this->_platform->supportsReleaseSavepoints()) {
            $this->_conn->exec($this->_platform->releaseSavePoint($savepoint));
        }
    }

    /**
     * rollbackSavePoint
     * releases given savepoint
     *
     * @param string $savepoint     name of a savepoint to rollback to
     * @return void
     */
    public function rollbackSavepoint($savepoint)
    {
        if ( ! $this->_platform->supportsSavepoints()) {
            throw ConnectionException::savepointsNotSupported();
        }

        $this->_conn->exec($this->_platform->rollbackSavePoint($savepoint));
    }

    /**
     * Gets the wrapped driver connection.
     *
     * @return \Doctrine\DBAL\Driver\Connection
     */
    public function getWrappedConnection()
    {
        $this->connect();

        return $this->_conn;
    }

    /**
     * Gets the SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     *
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    public function getSchemaManager()
    {
        if ( ! $this->_schemaManager) {
            $this->_schemaManager = $this->_driver->getSchemaManager($this);
        }

        return $this->_schemaManager;
    }

    /**
     * Marks the current transaction so that the only possible
     * outcome for the transaction to be rolled back.
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function setRollbackOnly()
    {
        if ($this->_transactionNestingLevel == 0) {
            throw ConnectionException::noActiveTransaction();
        }
        $this->_isRollbackOnly = true;
    }

    /**
     * Check whether the current transaction is marked for rollback only.
     *
     * @return boolean
     * @throws ConnectionException If no transaction is active.
     */
    public function isRollbackOnly()
    {
        if ($this->_transactionNestingLevel == 0) {
            throw ConnectionException::noActiveTransaction();
        }
        return $this->_isRollbackOnly;
    }

    /**
     * Converts a given value to its database representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed $value The value to convert.
     * @param string $type The name of the DBAL mapping type.
     * @return mixed The converted value.
     */
    public function convertToDatabaseValue($value, $type)
    {
        return Type::getType($type)->convertToDatabaseValue($value, $this->_platform);
    }

    /**
     * Converts a given value to its PHP representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed $value The value to convert.
     * @param string $type The name of the DBAL mapping type.
     * @return mixed The converted type.
     */
    public function convertToPHPValue($value, $type)
    {
        return Type::getType($type)->convertToPHPValue($value, $this->_platform);
    }

    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type
     * or DBAL mapping type, to a given statement.
     *
     * @param string $stmt The statement to bind the values to.
     * @param array $params The map/list of named/positional parameters.
     * @param array $types The parameter types (PDO binding types or DBAL mapping types).
     * @internal Duck-typing used on the $stmt parameter to support driver statements as well as
     *           raw PDOStatement instances.
     */
    private function _bindTypedValues($stmt, array $params, array $types)
    {
        // Check whether parameters are positional or named. Mixing is not allowed, just like in PDO.
        if (is_int(key($params))) {
            // Positional parameters
            $typeOffset = array_key_exists(0, $types) ? -1 : 0;
            $bindIndex = 1;
            foreach ($params as $value) {
                $typeIndex = $bindIndex + $typeOffset;
                if (isset($types[$typeIndex])) {
                    $type = $types[$typeIndex];
                    list($value, $bindingType) = $this->getBindingInfo($value, $type);
                    $stmt->bindValue($bindIndex, $value, $bindingType);
                } else {
                    $stmt->bindValue($bindIndex, $value);
                }
                ++$bindIndex;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (isset($types[$name])) {
                    $type = $types[$name];
                    list($value, $bindingType) = $this->getBindingInfo($value, $type);
                    $stmt->bindValue($name, $value, $bindingType);
                } else {
                    $stmt->bindValue($name, $value);
                }
            }
        }
    }

    /**
     * Gets the binding type of a given type. The given type can be a PDO or DBAL mapping type.
     *
     * @param mixed $value The value to bind
     * @param mixed $type The type to bind (PDO or DBAL)
     * @return array [0] => the (escaped) value, [1] => the binding type
     */
    private function getBindingInfo($value, $type)
    {
        if (is_string($type)) {
            $type = Type::getType($type);
        }
        if ($type instanceof Type) {
            $value = $type->convertToDatabaseValue($value, $this->_platform);
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type; // PDO::PARAM_* constants
        }
        return array($value, $bindingType);
    }

    /**
     * Resolves the parameters to a format which can be displayed.
     *
     * @internal This is a purely internal method. If you rely on this method, you are advised to
     *           copy/paste the code as this method may change, or be removed without prior notice.
     *
     * @param array $params
     * @param array $types
     *
     * @return array
     */
    public function resolveParams(array $params, array $types)
    {
        $resolvedParams = array();

        // Check whether parameters are positional or named. Mixing is not allowed, just like in PDO.
        if (is_int(key($params))) {
            // Positional parameters
            $typeOffset = array_key_exists(0, $types) ? -1 : 0;
            $bindIndex = 1;
            foreach ($params as $value) {
                $typeIndex = $bindIndex + $typeOffset;
                if (isset($types[$typeIndex])) {
                    $type = $types[$typeIndex];
                    list($value,) = $this->getBindingInfo($value, $type);
                    $resolvedParams[$bindIndex] = $value;
                } else {
                    $resolvedParams[$bindIndex] = $value;
                }
                ++$bindIndex;
            }
        } else {
            // Named parameters
            foreach ($params as $name => $value) {
                if (isset($types[$name])) {
                    $type = $types[$name];
                    list($value,) = $this->getBindingInfo($value, $type);
                    $resolvedParams[$name] = $value;
                } else {
                    $resolvedParams[$name] = $value;
                }
            }
        }

        return $resolvedParams;
    }

    /**
     * Create a new instance of a SQL query builder.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new Query\QueryBuilder($this);
    }
}

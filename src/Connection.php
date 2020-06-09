<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Closure;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Abstraction\Result as AbstractionResult;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\CacheException;
use Doctrine\DBAL\Cache\CachingResult;
use Doctrine\DBAL\Cache\Exception\NoResultDriverConfigured;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception\CommitFailedRollbackOnly;
use Doctrine\DBAL\Exception\EmptyCriteriaNotAllowed;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\InvalidPlatformType;
use Doctrine\DBAL\Exception\MayNotAlterNestedTransactionWithSavepointsInTransaction;
use Doctrine\DBAL\Exception\NoActiveTransaction;
use Doctrine\DBAL\Exception\SavepointsNotSupported;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Types\Type;
use Exception;
use Throwable;
use Traversable;

use function array_key_exists;
use function assert;
use function count;
use function implode;
use function is_int;
use function is_string;
use function key;

/**
 * A wrapper around a Doctrine\DBAL\Driver\Connection that adds features like
 * events, transaction isolation levels, configuration, emulated transaction nesting,
 * lazy connecting and more.
 */
class Connection implements DriverConnection
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_INT_ARRAY = ParameterType::INTEGER + self::ARRAY_PARAM_OFFSET;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_STR_ARRAY = ParameterType::STRING + self::ARRAY_PARAM_OFFSET;

    /**
     * Offset by which PARAM_* constants are detected as arrays of the param type.
     */
    public const ARRAY_PARAM_OFFSET = 100;

    /**
     * The wrapped driver connection.
     *
     * @var \Doctrine\DBAL\Driver\Connection|null
     */
    protected $_conn;

    /** @var Configuration */
    protected $_config;

    /** @var EventManager */
    protected $_eventManager;

    /** @var ExpressionBuilder */
    protected $_expr;

    /**
     * Whether or not a connection has been established.
     *
     * @var bool
     */
    private $isConnected = false;

    /**
     * The current auto-commit mode of this connection.
     *
     * @var bool
     */
    private $autoCommit = true;

    /**
     * The transaction nesting level.
     *
     * @var int
     */
    private $transactionNestingLevel = 0;

    /**
     * The currently active transaction isolation level.
     *
     * @var int
     */
    private $transactionIsolationLevel;

    /**
     * If nested transactions should use savepoints.
     *
     * @var bool
     */
    private $nestTransactionsWithSavepoints = false;

    /**
     * The parameters used during creation of the Connection instance.
     *
     * @var array<string, mixed>
     */
    private $params = [];

    /**
     * The DatabasePlatform object that provides information about the
     * database platform used by the connection.
     *
     * @var AbstractPlatform
     */
    private $platform;

    /**
     * The schema manager.
     *
     * @var AbstractSchemaManager|null
     */
    protected $_schemaManager;

    /**
     * The used DBAL driver.
     *
     * @var Driver
     */
    protected $_driver;

    /**
     * Flag that indicates whether the current transaction is marked for rollback only.
     *
     * @var bool
     */
    private $isRollbackOnly = false;

    /**
     * Initializes a new instance of the Connection class.
     *
     * @param array<string, mixed> $params       The connection parameters.
     * @param Driver               $driver       The driver to use.
     * @param Configuration|null   $config       The configuration, optional.
     * @param EventManager|null    $eventManager The event manager, optional.
     *
     * @throws DBALException
     */
    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        $this->_driver = $driver;
        $this->params  = $params;

        if (isset($params['platform'])) {
            if (! $params['platform'] instanceof Platforms\AbstractPlatform) {
                throw InvalidPlatformType::new($params['platform']);
            }

            $this->platform = $params['platform'];
        }

        // Create default config and event manager if none given
        if ($config === null) {
            $config = new Configuration();
        }

        if ($eventManager === null) {
            $eventManager = new EventManager();
        }

        $this->_config       = $config;
        $this->_eventManager = $eventManager;

        $this->_expr = new Query\Expression\ExpressionBuilder($this);

        $this->autoCommit = $config->getAutoCommit();
    }

    /**
     * Gets the parameters used during instantiation.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Gets the name of the currently selected database.
     *
     * @return string|null The name of the database or NULL if a database is not selected.
     *                     The platforms which don't support the concept of a database (e.g. embedded databases)
     *                     must always return a string as an indicator of an implicitly selected database.
     *
     * @throws DBALException
     */
    public function getDatabase(): ?string
    {
        $platform = $this->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getCurrentDatabaseExpression());
        $database = $this->query($query)->fetchOne();

        assert(is_string($database) || $database === null);

        return $database;
    }

    /**
     * Gets the DBAL driver instance.
     */
    public function getDriver(): Driver
    {
        return $this->_driver;
    }

    /**
     * Gets the Configuration used by the Connection.
     */
    public function getConfiguration(): Configuration
    {
        return $this->_config;
    }

    /**
     * Gets the EventManager used by the Connection.
     */
    public function getEventManager(): EventManager
    {
        return $this->_eventManager;
    }

    /**
     * Gets the DatabasePlatform for the connection.
     *
     * @throws DBALException
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        if ($this->platform === null) {
            $this->detectDatabasePlatform();
        }

        return $this->platform;
    }

    /**
     * Gets the ExpressionBuilder for the connection.
     */
    public function getExpressionBuilder(): ExpressionBuilder
    {
        return $this->_expr;
    }

    /**
     * Establishes the connection with the database.
     *
     * @throws DriverException
     */
    public function connect(): void
    {
        if ($this->isConnected) {
            return;
        }

        $driverOptions = $this->params['driverOptions'] ?? [];
        $user          = $this->params['user'] ?? '';
        $password      = $this->params['password'] ?? '';

        $this->_conn       = $this->_driver->connect($this->params, $user, $password, $driverOptions);
        $this->isConnected = true;

        $this->transactionNestingLevel = 0;

        if ($this->autoCommit === false) {
            $this->beginTransaction();
        }

        if (! $this->_eventManager->hasListeners(Events::postConnect)) {
            return;
        }

        $eventArgs = new Event\ConnectionEventArgs($this);
        $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
    }

    /**
     * Detects and sets the database platform.
     *
     * Evaluates custom platform class and version in order to set the correct platform.
     *
     * @throws DBALException If an invalid platform was specified for this connection.
     */
    private function detectDatabasePlatform(): void
    {
        $version = $this->getDatabasePlatformVersion();

        if ($version !== null) {
            assert($this->_driver instanceof VersionAwarePlatformDriver);

            $this->platform = $this->_driver->createDatabasePlatformForVersion($version);
        } else {
            $this->platform = $this->_driver->getDatabasePlatform();
        }

        $this->platform->setEventManager($this->_eventManager);
    }

    /**
     * Returns the version of the related platform if applicable.
     *
     * Returns null if either the driver is not capable to create version
     * specific platform instances, no explicit server version was specified
     * or the underlying driver connection cannot determine the platform
     * version without having to query it (performance reasons).
     *
     * @throws Exception
     */
    private function getDatabasePlatformVersion(): ?string
    {
        // Driver does not support version specific platforms.
        if (! $this->_driver instanceof VersionAwarePlatformDriver) {
            return null;
        }

        // Explicit platform version requested (supersedes auto-detection).
        if (isset($this->params['serverVersion'])) {
            return $this->params['serverVersion'];
        }

        // If not connected, we need to connect now to determine the platform version.
        if ($this->_conn === null) {
            try {
                $this->connect();
            } catch (Throwable $originalException) {
                if (! isset($this->params['dbname'])) {
                    throw $originalException;
                }

                // The database to connect to might not yet exist.
                // Retry detection without database name connection parameter.
                $databaseName           = $this->params['dbname'];
                $this->params['dbname'] = null;

                try {
                    $this->connect();
                } catch (Throwable $fallbackException) {
                    // Either the platform does not support database-less connections
                    // or something else went wrong.
                    // Reset connection parameters and rethrow the original exception.
                    $this->params['dbname'] = $databaseName;

                    throw $originalException;
                }

                // Reset connection parameters.
                $this->params['dbname'] = $databaseName;
                $serverVersion          = $this->getServerVersion();

                // Close "temporary" connection to allow connecting to the real database again.
                $this->close();

                return $serverVersion;
            }
        }

        return $this->getServerVersion();
    }

    /**
     * Returns the database server version if the underlying driver supports it.
     */
    private function getServerVersion(): ?string
    {
        $connection = $this->getWrappedConnection();

        // Automatic platform version detection.
        if ($connection instanceof ServerInfoAwareConnection) {
            return $connection->getServerVersion();
        }

        // Unable to detect platform version.
        return null;
    }

    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @see    setAutoCommit
     *
     * @return bool True if auto-commit mode is currently enabled for this connection, false otherwise.
     */
    public function isAutoCommit(): bool
    {
        return $this->autoCommit;
    }

    /**
     * Sets auto-commit mode for this connection.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * NOTE: If this method is called during a transaction and the auto-commit mode is changed, the transaction is
     * committed. If this method is called and the auto-commit mode is not changed, the call is a no-op.
     *
     * @see isAutoCommit
     *
     * @throws ConnectionException
     * @throws DriverException
     */
    public function setAutoCommit(bool $autoCommit): void
    {
        // Mode not changed, no-op.
        if ($autoCommit === $this->autoCommit) {
            return;
        }

        $this->autoCommit = $autoCommit;

        // Commit all currently active transactions if any when switching auto-commit mode.
        if (! $this->isConnected || $this->transactionNestingLevel === 0) {
            return;
        }

        $this->commitAll();
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The prepared statement params.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws DBALException
     */
    public function fetchAssociative(string $query, array $params = [], array $types = [])
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchAssociative();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @param string                                           $query  The SQL query to be executed.
     * @param array<int, mixed>|array<string, mixed>           $params The prepared statement params.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return array<int, mixed>|false False is returned if no rows are found.
     *
     * @throws DBALException
     */
    public function fetchNumeric(string $query, array $params = [], array $types = [])
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchNumeric();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param string                                           $query  The SQL query to be executed.
     * @param array<int, mixed>|array<string, mixed>           $params The prepared statement params.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws DBALException
     */
    public function fetchOne(string $query, array $params = [], array $types = [])
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchOne();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Whether an actual connection to the database is established.
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive(): bool
    {
        return $this->transactionNestingLevel > 0;
    }

    /**
     * Adds identifier condition to the query components
     *
     * @param array<string, mixed> $identifier Map of key columns to their values
     * @param array<int, string>   $columns    Column names
     * @param array<int, mixed>    $values     Column values
     * @param array<int, string>   $conditions Key conditions
     *
     * @throws DBALException
     */
    private function addIdentifierCondition(
        array $identifier,
        array &$columns,
        array &$values,
        array &$conditions
    ): void {
        $platform = $this->getDatabasePlatform();

        foreach ($identifier as $columnName => $value) {
            if ($value === null) {
                $conditions[] = $platform->getIsNullExpression($columnName);
                continue;
            }

            $columns[]    = $columnName;
            $values[]     = $value;
            $conditions[] = $columnName . ' = ?';
        }
    }

    /**
     * Executes an SQL DELETE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string                        $table      The SQL expression of the table on which to delete.
     * @param array<string, mixed>          $identifier The deletion criteria. An associative array containing column-value pairs.
     * @param array<int|string, int|string> $types      The query parameter types.
     *
     * @return int The number of affected rows.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function delete(string $table, array $identifier, array $types = []): int
    {
        if (count($identifier) === 0) {
            throw EmptyCriteriaNotAllowed::new();
        }

        $columns = $values = $conditions = [];

        $this->addIdentifierCondition($identifier, $columns, $values, $conditions);

        return $this->executeUpdate(
            'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $conditions),
            $values,
            is_string(key($types)) ? $this->extractTypeValues($columns, $types) : $types
        );
    }

    /**
     * Closes the connection.
     */
    public function close(): void
    {
        $this->_conn = null;

        $this->isConnected = false;
    }

    /**
     * Sets the transaction isolation level.
     *
     * @param int $level The level to set.
     */
    public function setTransactionIsolation(int $level): void
    {
        $this->transactionIsolationLevel = $level;

        $this->executeUpdate($this->getDatabasePlatform()->getSetTransactionIsolationSQL($level));
    }

    /**
     * Gets the currently active transaction isolation level.
     *
     * @return int The current transaction isolation level.
     */
    public function getTransactionIsolation(): int
    {
        if ($this->transactionIsolationLevel === null) {
            $this->transactionIsolationLevel = $this->getDatabasePlatform()->getDefaultTransactionIsolationLevel();
        }

        return $this->transactionIsolationLevel;
    }

    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string                                           $table      The SQL expression of the table to update quoted or unquoted.
     * @param array<string, mixed>                             $data       An associative array containing column-value pairs.
     * @param array<string, mixed>                             $identifier The update criteria. An associative array containing column-value pairs.
     * @param array<int, int|string>|array<string, int|string> $types      The query parameter types.
     *
     * @return int The number of affected rows.
     *
     * @throws DBALException
     */
    public function update(string $table, array $data, array $identifier, array $types = []): int
    {
        $columns = $values = $conditions = $set = [];

        foreach ($data as $columnName => $value) {
            $columns[] = $columnName;
            $values[]  = $value;
            $set[]     = $columnName . ' = ?';
        }

        $this->addIdentifierCondition($identifier, $columns, $values, $conditions);

        if (is_string(key($types))) {
            $types = $this->extractTypeValues($columns, $types);
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set)
                . ' WHERE ' . implode(' AND ', $conditions);

        return $this->executeUpdate($sql, $values, $types);
    }

    /**
     * Inserts a table row with specified data.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string                                           $table The SQL expression of the table to insert data into, quoted or unquoted.
     * @param array<string, mixed>                             $data  An associative array containing column-value pairs.
     * @param array<int, int|string>|array<string, int|string> $types The query parameter types.
     *
     * @return int The number of affected rows.
     *
     * @throws DBALException
     */
    public function insert(string $table, array $data, array $types = []): int
    {
        if (count($data) === 0) {
            return $this->executeUpdate('INSERT INTO ' . $table . ' () VALUES ()');
        }

        $columns = [];
        $values  = [];
        $set     = [];

        foreach ($data as $columnName => $value) {
            $columns[] = $columnName;
            $values[]  = $value;
            $set[]     = '?';
        }

        return $this->executeUpdate(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')' .
            ' VALUES (' . implode(', ', $set) . ')',
            $values,
            is_string(key($types)) ? $this->extractTypeValues($columns, $types) : $types
        );
    }

    /**
     * Extract ordered type list from an ordered column list and type map.
     *
     * @param array<int, string>            $columnList
     * @param array<int|string, int|string> $types      The query parameter types.
     *
     * @return array<int, int>|array<int, string>
     */
    private function extractTypeValues(array $columnList, array $types)
    {
        $typeValues = [];

        foreach ($columnList as $columnName) {
            $typeValues[] = $types[$columnName] ?? ParameterType::STRING;
        }

        return $typeValues;
    }

    /**
     * Quotes a string so it can be safely used as a table or column name, even if
     * it is a reserved name.
     *
     * Delimiting style depends on the underlying database platform that is being used.
     *
     * NOTE: Just because you CAN use quoted identifiers does not mean
     * you SHOULD use them. In general, they end up causing way more
     * problems than they solve.
     *
     * @param string $identifier The identifier to be quoted.
     *
     * @return string The quoted identifier.
     */
    public function quoteIdentifier(string $identifier): string
    {
        return $this->getDatabasePlatform()->quoteIdentifier($identifier);
    }

    public function quote(string $input): string
    {
        return $this->getWrappedConnection()->quote($input);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return array<int,array<int,mixed>>
     *
     * @throws DBALException
     */
    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchAllNumeric();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return array<int,array<string,mixed>>
     *
     * @throws DBALException
     */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchAllAssociative();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return array<int,mixed>
     *
     * @throws DBALException
     */
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->executeQuery($query, $params, $types)->fetchFirstColumn();
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return Traversable<int,array<int,mixed>>
     *
     * @throws DBALException
     */
    public function iterateNumeric(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            $result = $this->executeQuery($query, $params, $types);

            while (($row = $result->fetchNumeric()) !== false) {
                yield $row;
            }
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as associative arrays.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws DBALException
     */
    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            $result = $this->executeQuery($query, $params, $types);

            while (($row = $result->fetchAssociative()) !== false) {
                yield $row;
            }
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @return Traversable<int,mixed>
     *
     * @throws DBALException
     */
    public function iterateColumn(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            $result = $this->executeQuery($query, $params, $types);

            while (($value = $result->fetchOne()) !== false) {
                yield $value;
            }
        } catch (Throwable $e) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $e, $query);
        }
    }

    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement to prepare.
     *
     * @throws DBALException
     */
    public function prepare(string $sql): DriverStatement
    {
        try {
            return new Statement($sql, $this);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $sql);
        }
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     * If an SQLLogger is configured, the execution is logged.
     *
     * @param string                                           $query  The SQL query to execute.
     * @param array<int, mixed>|array<string, mixed>           $params The parameters to bind to the query, if any.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     * @param QueryCacheProfile|null                           $qcp    The query cache profile, optional.
     *
     * @throws DBALException
     */
    public function executeQuery(
        string $query,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null
    ): AbstractionResult {
        if ($qcp !== null) {
            return $this->executeCacheQuery($query, $params, $types, $qcp);
        }

        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        $logger->startQuery($query, $params, $types);

        try {
            if (count($params) > 0) {
                [$query, $params, $types] = SQLParserUtils::expandListParameters($query, $params, $types);

                $stmt = $connection->prepare($query);
                if (count($types) > 0) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $result = $stmt->execute();
                } else {
                    $result = $stmt->execute($params);
                }
            } else {
                $result = $connection->query($query);
            }

            return new Result($result, $this);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $query, $this->resolveParams($params, $types));
        } finally {
            $logger->stopQuery();
        }
    }

    /**
     * Executes a caching query.
     *
     * @param string                                           $query  The SQL query to execute.
     * @param array<int, mixed>|array<string, mixed>           $params The parameters to bind to the query, if any.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     * @param QueryCacheProfile                                $qcp    The query cache profile.
     *
     * @throws CacheException
     * @throws DBALException
     */
    public function executeCacheQuery(string $query, array $params, array $types, QueryCacheProfile $qcp): Result
    {
        $resultCache = $qcp->getResultCacheDriver() ?? $this->_config->getResultCacheImpl();

        if ($resultCache === null) {
            throw NoResultDriverConfigured::new();
        }

        $connectionParams = $this->getParams();
        unset($connectionParams['platform']);

        [$cacheKey, $realKey] = $qcp->generateCacheKeys($query, $params, $types, $connectionParams);

        // fetch the row pointers entry
        $data = $resultCache->fetch($cacheKey);

        if ($data !== false) {
            // is the real key part of this row pointers map or is the cache only pointing to other cache keys?
            if (isset($data[$realKey])) {
                $result = new ArrayResult($data[$realKey]);
            } elseif (array_key_exists($realKey, $data)) {
                $result = new ArrayResult([]);
            }
        }

        if (! isset($result)) {
            $result = new CachingResult(
                $this->executeQuery($query, $params, $types),
                $resultCache,
                $cacheKey,
                $realKey,
                $qcp->getLifetime()
            );
        }

        return new Result($result, $this);
    }

    public function query(string $sql): DriverResult
    {
        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        $logger->startQuery($sql);

        try {
            return $connection->query($sql);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $sql);
        } finally {
            $logger->stopQuery();
        }
    }

    /**
     * Executes an SQL INSERT/UPDATE/DELETE query with the given parameters
     * and returns the number of affected rows.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                                           $query  The SQL query.
     * @param array<int, mixed>|array<string, mixed>           $params The query parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     *
     * @throws DBALException
     */
    public function executeUpdate(string $query, array $params = [], array $types = []): int
    {
        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        $logger->startQuery($query, $params, $types);

        try {
            if (count($params) > 0) {
                [$query, $params, $types] = SQLParserUtils::expandListParameters($query, $params, $types);

                $stmt = $connection->prepare($query);

                if (count($types) > 0) {
                    $this->_bindTypedValues($stmt, $params, $types);

                    $result = $stmt->execute();
                } else {
                    $result = $stmt->execute($params);
                }

                return $result->rowCount();
            }

            return $connection->exec($query);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $query, $this->resolveParams($params, $types));
        } finally {
            $logger->stopQuery();
        }
    }

    public function exec(string $statement): int
    {
        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();
        $logger->startQuery($statement);

        try {
            return $connection->exec($statement);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $statement);
        } finally {
            $logger->stopQuery();
        }
    }

    /**
     * Returns the current transaction nesting level.
     *
     * @return int The nesting level. A value of 0 means there's no active transaction.
     */
    public function getTransactionNestingLevel(): int
    {
        return $this->transactionNestingLevel;
    }

    /**
     * Returns the ID of the last inserted row, or the last value from a sequence object,
     * depending on the underlying driver.
     *
     * Note: This method may not return a meaningful or consistent result across different drivers,
     * because the underlying database may not even support the notion of AUTO_INCREMENT/IDENTITY
     * columns or sequences.
     *
     * @param string|null $name Name of the sequence object from which the ID should be returned.
     *
     * @return string A string representation of the last inserted ID.
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getWrappedConnection()->lastInsertId($name);
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
     *
     * @return mixed The value returned by $func
     *
     * @throws Throwable
     */
    public function transactional(Closure $func)
    {
        $this->beginTransaction();
        try {
            $res = $func($this);
            $this->commit();

            return $res;
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Sets if nested transactions should use savepoints.
     *
     * @throws ConnectionException
     */
    public function setNestTransactionsWithSavepoints(bool $nestTransactionsWithSavepoints): void
    {
        if ($this->transactionNestingLevel > 0) {
            throw MayNotAlterNestedTransactionWithSavepointsInTransaction::new();
        }

        if (! $this->getDatabasePlatform()->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        $this->nestTransactionsWithSavepoints = $nestTransactionsWithSavepoints;
    }

    /**
     * Gets if nested transactions should use savepoints.
     */
    public function getNestTransactionsWithSavepoints(): bool
    {
        return $this->nestTransactionsWithSavepoints;
    }

    /**
     * Returns the savepoint name to use for nested transactions are false if they are not supported
     * "savepointFormat" parameter is not set
     *
     * @return mixed A string with the savepoint name or false.
     */
    protected function _getNestedTransactionSavePointName()
    {
        return 'DOCTRINE2_SAVEPOINT_' . $this->transactionNestingLevel;
    }

    public function beginTransaction(): void
    {
        $connection = $this->getWrappedConnection();

        ++$this->transactionNestingLevel;

        $logger = $this->_config->getSQLLogger();

        if ($this->transactionNestingLevel === 1) {
            $logger->startQuery('"START TRANSACTION"');

            try {
                $connection->beginTransaction();
            } finally {
                $logger->stopQuery();
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger->startQuery('"SAVEPOINT"');
            $this->createSavepoint($this->_getNestedTransactionSavePointName());
            $logger->stopQuery();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the commit failed due to no active transaction or
     *                                            because the transaction was marked for rollback only.
     */
    public function commit(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        if ($this->isRollbackOnly) {
            throw CommitFailedRollbackOnly::new();
        }

        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();

        if ($this->transactionNestingLevel === 1) {
            $logger->startQuery('"COMMIT"');

            try {
                $connection->commit();
            } finally {
                $logger->stopQuery();
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger->startQuery('"RELEASE SAVEPOINT"');
            $this->releaseSavepoint($this->_getNestedTransactionSavePointName());
            $logger->stopQuery();
        }

        --$this->transactionNestingLevel;

        if ($this->autoCommit !== false || $this->transactionNestingLevel !== 0) {
            return;
        }

        $this->beginTransaction();
    }

    /**
     * Commits all current nesting transactions.
     *
     * @throws ConnectionException
     * @throws DriverException
     */
    private function commitAll(): void
    {
        while ($this->transactionNestingLevel !== 0) {
            if ($this->autoCommit === false && $this->transactionNestingLevel === 1) {
                // When in no auto-commit mode, the last nesting commit immediately starts a new transaction.
                // Therefore we need to do the final commit here and then leave to avoid an infinite loop.
                $this->commit();

                return;
            }

            $this->commit();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws ConnectionException If the rollback operation failed.
     */
    public function rollBack(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        $connection = $this->getWrappedConnection();

        $logger = $this->_config->getSQLLogger();

        if ($this->transactionNestingLevel === 1) {
            $logger->startQuery('"ROLLBACK"');
            $this->transactionNestingLevel = 0;

            try {
                $connection->rollBack();
            } finally {
                $this->isRollbackOnly = false;
                $logger->stopQuery();

                if ($this->autoCommit === false) {
                    $this->beginTransaction();
                }
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $logger->startQuery('"ROLLBACK TO SAVEPOINT"');
            $this->rollbackSavepoint($this->_getNestedTransactionSavePointName());
            --$this->transactionNestingLevel;
            $logger->stopQuery();
        } else {
            $this->isRollbackOnly = true;
            --$this->transactionNestingLevel;
        }
    }

    /**
     * Creates a new savepoint.
     *
     * @param string $savepoint The name of the savepoint to create.
     *
     * @throws ConnectionException
     */
    public function createSavepoint(string $savepoint): void
    {
        if (! $this->getDatabasePlatform()->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        $this->getWrappedConnection()->exec($this->platform->createSavePoint($savepoint));
    }

    /**
     * Releases the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to release.
     *
     * @throws ConnectionException
     */
    public function releaseSavepoint(string $savepoint): void
    {
        if (! $this->getDatabasePlatform()->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        if (! $this->platform->supportsReleaseSavepoints()) {
            return;
        }

        $this->getWrappedConnection()->exec($this->platform->releaseSavePoint($savepoint));
    }

    /**
     * Rolls back to the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to rollback to.
     *
     * @throws ConnectionException
     */
    public function rollbackSavepoint(string $savepoint): void
    {
        if (! $this->getDatabasePlatform()->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        $this->getWrappedConnection()->exec($this->platform->rollbackSavePoint($savepoint));
    }

    /**
     * Gets the wrapped driver connection.
     */
    public function getWrappedConnection(): DriverConnection
    {
        $this->connect();

        assert($this->_conn !== null);

        return $this->_conn;
    }

    /**
     * Gets the SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     */
    public function getSchemaManager(): AbstractSchemaManager
    {
        if ($this->_schemaManager === null) {
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
    public function setRollbackOnly(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        $this->isRollbackOnly = true;
    }

    /**
     * Checks whether the current transaction is marked for rollback only.
     *
     * @throws ConnectionException If no transaction is active.
     */
    public function isRollbackOnly(): bool
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        return $this->isRollbackOnly;
    }

    /**
     * Converts a given value to its database representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed  $value The value to convert.
     * @param string $type  The name of the DBAL mapping type.
     *
     * @return mixed The converted value.
     */
    public function convertToDatabaseValue($value, string $type)
    {
        return Type::getType($type)->convertToDatabaseValue($value, $this->getDatabasePlatform());
    }

    /**
     * Converts a given value to its PHP representation according to the conversion
     * rules of a specific DBAL mapping type.
     *
     * @param mixed  $value The value to convert.
     * @param string $type  The name of the DBAL mapping type.
     *
     * @return mixed The converted type.
     */
    public function convertToPHPValue($value, string $type)
    {
        return Type::getType($type)->convertToPHPValue($value, $this->getDatabasePlatform());
    }

    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type
     * or DBAL mapping type, to a given statement.
     *
     * @internal Duck-typing used on the $stmt parameter to support driver statements as well as
     *           raw PDOStatement instances.
     *
     * @param DriverStatement                                  $stmt   The statement to bind the values to.
     * @param array<int, mixed>|array<string, mixed>           $params The map/list of named/positional parameters.
     * @param array<int, int|string>|array<string, int|string> $types  The query parameter types.
     */
    private function _bindTypedValues(DriverStatement $stmt, array $params, array $types): void
    {
        // Check whether parameters are positional or named. Mixing is not allowed, just like in PDO.
        if (is_int(key($params))) {
            // Positional parameters
            $typeOffset = array_key_exists(0, $types) ? -1 : 0;
            $bindIndex  = 1;
            foreach ($params as $value) {
                $typeIndex = $bindIndex + $typeOffset;
                if (isset($types[$typeIndex])) {
                    $type                  = $types[$typeIndex];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
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
                    $type                  = $types[$name];
                    [$value, $bindingType] = $this->getBindingInfo($value, $type);
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
     * @param mixed                $value The value to bind.
     * @param int|string|Type|null $type  The type to bind (PDO or DBAL).
     *
     * @return array<int, mixed> [0] => the (escaped) value, [1] => the binding type.
     */
    private function getBindingInfo($value, $type): array
    {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value       = $type->convertToDatabaseValue($value, $this->getDatabasePlatform());
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type;
        }

        return [$value, $bindingType];
    }

    /**
     * Resolves the parameters to a format which can be displayed.
     *
     * @internal This is a purely internal method. If you rely on this method, you are advised to
     *           copy/paste the code as this method may change, or be removed without prior notice.
     *
     * @param array<int, mixed>|array<string, mixed>                               $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  The query parameter types.
     *
     * @return array<int, mixed>|array<string, mixed>
     */
    public function resolveParams(array $params, array $types): array
    {
        $resolvedParams = [];

        // Check whether parameters are positional or named. Mixing is not allowed, just like in PDO.
        if (is_int(key($params))) {
            // Positional parameters
            $typeOffset = array_key_exists(0, $types) ? -1 : 0;
            $bindIndex  = 1;
            foreach ($params as $value) {
                $typeIndex = $bindIndex + $typeOffset;
                if (isset($types[$typeIndex])) {
                    $type                       = $types[$typeIndex];
                    [$value]                    = $this->getBindingInfo($value, $type);
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
                    $type                  = $types[$name];
                    [$value]               = $this->getBindingInfo($value, $type);
                    $resolvedParams[$name] = $value;
                } else {
                    $resolvedParams[$name] = $value;
                }
            }
        }

        return $resolvedParams;
    }

    /**
     * Creates a new instance of a SQL query builder.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new Query\QueryBuilder($this);
    }

    /**
     * Ping the server
     *
     * When the server is not available the method throws a DBALException.
     * It is responsibility of the developer to handle this case
     * and abort the request or close the connection so that it's reestablished
     * upon the next statement execution.
     *
     * @throws DBALException
     *
     * @example
     *
     *   try {
     *      $conn->ping();
     *   } catch (DBALException $e) {
     *      $conn->close();
     *   }
     *
     * It is undefined if the underlying driver attempts to reconnect
     * or disconnect when the connection is not available anymore
     * as long it successfully returns when a reconnect succeeded
     * and throws an exception if the connection was dropped.
     */
    public function ping(): void
    {
        $connection = $this->getWrappedConnection();

        if (! $connection instanceof PingableConnection) {
            $this->query($this->getDatabasePlatform()->getDummySelectSQL());

            return;
        }

        try {
            $connection->ping();
        } catch (DriverException $e) {
            throw DBALException::driverException($this->_driver, $e);
        }
    }
}

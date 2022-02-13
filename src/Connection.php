<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Closure;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\CacheException;
use Doctrine\DBAL\Cache\Exception\NoResultDriverConfigured;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection\StaticServerVersionProvider;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Event\TransactionBeginEventArgs;
use Doctrine\DBAL\Event\TransactionCommitEventArgs;
use Doctrine\DBAL\Event\TransactionRollBackEventArgs;
use Doctrine\DBAL\Exception\CommitFailedRollbackOnly;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\EmptyCriteriaNotAllowed;
use Doctrine\DBAL\Exception\InvalidPlatformType;
use Doctrine\DBAL\Exception\MayNotAlterNestedTransactionWithSavepointsInTransaction;
use Doctrine\DBAL\Exception\NoActiveTransaction;
use Doctrine\DBAL\Exception\SavepointsNotSupported;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Types\Type;
use Throwable;
use Traversable;

use function assert;
use function count;
use function implode;
use function is_int;
use function is_string;
use function key;

/**
 * A database abstraction-level connection that implements features like events, transaction isolation levels,
 * configuration, emulated transaction nesting, lazy connecting and more.
 *
 * @psalm-import-type Params from DriverManager
 * @psalm-consistent-constructor
 */
class Connection implements ServerVersionProvider
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
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    public const PARAM_ASCII_STR_ARRAY = ParameterType::ASCII + self::ARRAY_PARAM_OFFSET;

    /**
     * Offset by which PARAM_* constants are detected as arrays of the param type.
     */
    public const ARRAY_PARAM_OFFSET = 100;

    /**
     * The wrapped driver connection.
     */
    protected ?DriverConnection $_conn = null;

    protected Configuration $_config;

    protected EventManager $_eventManager;

    /**
     * The current auto-commit mode of this connection.
     */
    private bool $autoCommit = true;

    /**
     * The transaction nesting level.
     */
    private int $transactionNestingLevel = 0;

    /**
     * The currently active transaction isolation level or NULL before it has been determined.
     */
    private ?int $transactionIsolationLevel = null;

    /**
     * If nested transactions should use savepoints.
     */
    private bool $nestTransactionsWithSavepoints = false;

    /**
     * The parameters used during creation of the Connection instance.
     *
     * @var array<string,mixed>
     * @psalm-var Params
     */
    private array $params;

    /**
     * The database platform object used by the connection or NULL before it's initialized.
     */
    private ?AbstractPlatform $platform = null;

    private ?ExceptionConverter $exceptionConverter = null;

    private ?Parser $parser = null;

    /**
     * The used DBAL driver.
     */
    protected Driver $_driver;

    /**
     * Flag that indicates whether the current transaction is marked for rollback only.
     */
    private bool $isRollbackOnly = false;

    /**
     * Initializes a new instance of the Connection class.
     *
     * @internal The connection can be only instantiated by the driver manager.
     *
     * @param array<string, mixed> $params       The connection parameters.
     * @param Driver               $driver       The driver to use.
     * @param Configuration|null   $config       The configuration, optional.
     * @param EventManager|null    $eventManager The event manager, optional.
     * @psalm-param Params $params
     * @phpstan-param array<string,mixed> $params
     *
     * @throws Exception
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

        $this->autoCommit = $config->getAutoCommit();
    }

    /**
     * Gets the parameters used during instantiation.
     *
     * @internal
     *
     * @return array<string,mixed>
     * @psalm-return Params
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
     * @throws Exception
     */
    public function getDatabase(): ?string
    {
        $platform = $this->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getCurrentDatabaseExpression());
        $database = $this->fetchOne($query);

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
     * @throws Exception
     */
    public function getDatabasePlatform(): AbstractPlatform
    {
        if ($this->platform === null) {
            $versionProvider = $this;

            if (isset($this->params['serverVersion'])) {
                $versionProvider = new StaticServerVersionProvider($this->params['serverVersion']);
            }

            $this->platform = $this->_driver->getDatabasePlatform($versionProvider);
            $this->platform->setEventManager($this->_eventManager);
        }

        return $this->platform;
    }

    /**
     * Creates an expression builder for the connection.
     */
    public function createExpressionBuilder(): ExpressionBuilder
    {
        return new ExpressionBuilder($this);
    }

    /**
     * Establishes the connection with the database and returns the underlying connection.
     *
     * @throws Exception
     */
    protected function connect(): DriverConnection
    {
        if ($this->_conn !== null) {
            return $this->_conn;
        }

        try {
            $connection = $this->_conn = $this->_driver->connect($this->params);
        } catch (Driver\Exception $e) {
            throw $this->convertException($e);
        }

        if ($this->autoCommit === false) {
            $this->beginTransaction();
        }

        if ($this->_eventManager->hasListeners(Events::postConnect)) {
            $eventArgs = new Event\ConnectionEventArgs($this);
            $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
        }

        return $connection;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getServerVersion(): string
    {
        $connection = $this->getServerVersionConnection();

        try {
            return $connection->getServerVersion();
        } catch (Driver\Exception $e) {
            throw $this->convertException($e);
        }
    }

    /**
     * Returns the driver-level connection for server version detection.
     *
     * @throws Exception
     */
    private function getServerVersionConnection(): DriverConnection
    {
        try {
            return $this->connect();
        } catch (Exception $e) {
            if (! isset($this->params['dbname'])) {
                throw $e;
            }

            // The database to connect to might not yet exist.
            // Retry detection without database name connection parameter.
            $params = $this->params;

            unset($this->params['dbname']);

            try {
                return $this->connect();
            } catch (Exception $_) {
                // Either the platform does not support database-less connections
                // or something else went wrong.
                throw $e;
            } finally {
                $this->close();
                $this->params = $params;
            }
        }
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
        if ($this->_conn === null || $this->transactionNestingLevel === 0) {
            return;
        }

        $this->commitAll();
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        return $this->executeQuery($query, $params, $types)->fetchAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return list<mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchNumeric(string $query, array $params = [], array $types = [])
    {
        return $this->executeQuery($query, $params, $types)->fetchNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        return $this->executeQuery($query, $params, $types)->fetchOne();
    }

    /**
     * Whether an actual connection to the database is established.
     */
    public function isConnected(): bool
    {
        return $this->_conn !== null;
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
     * Adds condition based on the criteria to the query components
     *
     * @param array<string, mixed> $criteria   Map of key columns to their values
     * @param array<int, string>   $columns    Column names
     * @param array<int, mixed>    $values     Column values
     * @param array<int, string>   $conditions Key conditions
     *
     * @throws Exception
     */
    private function addCriteriaCondition(
        array $criteria,
        array &$columns,
        array &$values,
        array &$conditions
    ): void {
        foreach ($criteria as $columnName => $value) {
            if ($value === null) {
                $conditions[] = $columnName . ' IS NULL';
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
     * @param array<string, mixed>                                                 $criteria Deletion criteria
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types    Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     */
    public function delete(string $table, array $criteria, array $types = []): int|string
    {
        if (count($criteria) === 0) {
            throw EmptyCriteriaNotAllowed::new();
        }

        $columns = $values = $conditions = [];

        $this->addCriteriaCondition($criteria, $columns, $values, $conditions);

        return $this->executeStatement(
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
        $this->_conn                   = null;
        $this->transactionNestingLevel = 0;
    }

    /**
     * Sets the transaction isolation level.
     *
     * @param int $level The level to set.
     *
     * @throws Exception
     */
    public function setTransactionIsolation(int $level): void
    {
        $this->transactionIsolationLevel = $level;

        $this->executeStatement($this->getDatabasePlatform()->getSetTransactionIsolationSQL($level));
    }

    /**
     * Gets the currently active transaction isolation level.
     *
     * @return int The current transaction isolation level.
     *
     * @throws Exception
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
     * @param array<string, mixed>                                                 $data     Column-value pairs
     * @param array<string, mixed>                                                 $criteria Update criteria
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types    Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     */
    public function update(string $table, array $data, array $criteria, array $types = []): int|string
    {
        $columns = $values = $conditions = $set = [];

        foreach ($data as $columnName => $value) {
            $columns[] = $columnName;
            $values[]  = $value;
            $set[]     = $columnName . ' = ?';
        }

        $this->addCriteriaCondition($criteria, $columns, $values, $conditions);

        if (is_string(key($types))) {
            $types = $this->extractTypeValues($columns, $types);
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set)
                . ' WHERE ' . implode(' AND ', $conditions);

        return $this->executeStatement($sql, $values, $types);
    }

    /**
     * Inserts a table row with specified data.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param array<string, mixed>                                                 $data  Column-value pairs
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types Parameter types
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     */
    public function insert(string $table, array $data, array $types = []): int|string
    {
        if (count($data) === 0) {
            return $this->executeStatement('INSERT INTO ' . $table . ' () VALUES ()');
        }

        $columns = [];
        $values  = [];
        $set     = [];

        foreach ($data as $columnName => $value) {
            $columns[] = $columnName;
            $values[]  = $value;
            $set[]     = '?';
        }

        return $this->executeStatement(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')' .
            ' VALUES (' . implode(', ', $set) . ')',
            $values,
            is_string(key($types)) ? $this->extractTypeValues($columns, $types) : $types
        );
    }

    /**
     * Extract ordered type list from an ordered column list and type map.
     *
     * @param array<int, string>                                                   $columnList
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     *
     * @return array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    private function extractTypeValues(array $columnList, array $types): array
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

    /**
     * The usage of this method is discouraged. Use prepared statements
     * or {@see AbstractPlatform::quoteStringLiteral()} instead.
     */
    public function quote(string $value): string
    {
        return $this->connect()->quote($value);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return list<list<mixed>>
     *
     * @throws Exception
     */
    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        return $this->executeQuery($query, $params, $types)->fetchAllNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return list<array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        return $this->executeQuery($query, $params, $types)->fetchAllAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetchAllKeyValue(string $query, array $params = [], array $types = []): array
    {
        return $this->executeQuery($query, $params, $types)->fetchAllKeyValue();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param string                                                               $query  SQL query
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array
    {
        return $this->executeQuery($query, $params, $types)->fetchAllAssociativeIndexed();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return list<mixed>
     *
     * @throws Exception
     */
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return $this->executeQuery($query, $params, $types)->fetchFirstColumn();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return Traversable<int,list<mixed>>
     *
     * @throws Exception
     */
    public function iterateNumeric(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->executeQuery($query, $params, $types)->iterateNumeric();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->executeQuery($query, $params, $types)->iterateAssociative();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws Exception
     */
    public function iterateKeyValue(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->executeQuery($query, $params, $types)->iterateKeyValue();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @param string                                           $query  SQL query
     * @param list<mixed>|array<string, mixed>                 $params Query parameters
     * @param array<int, int|string>|array<string, int|string> $types  Parameter types
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->executeQuery($query, $params, $types)->iterateAssociativeIndexed();
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     */
    public function iterateColumn(string $query, array $params = [], array $types = []): Traversable
    {
        return $this->executeQuery($query, $params, $types)->iterateColumn();
    }

    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement to prepare.
     *
     * @throws Exception
     */
    public function prepare(string $sql): Statement
    {
        $connection = $this->connect();

        try {
            $statement = $connection->prepare($sql);
        } catch (Driver\Exception $e) {
            throw $this->convertExceptionDuringQuery($e, $sql);
        }

        return new Statement($this, $statement, $sql);
    }

    /**
     * Executes an, optionally parametrized, SQL query.
     *
     * If the query is parametrized, a prepared statement is used.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @throws Exception
     */
    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null
    ): Result {
        if ($qcp !== null) {
            return $this->executeCacheQuery($sql, $params, $types, $qcp);
        }

        $connection = $this->connect();

        try {
            if (count($params) > 0) {
                if ($this->needsArrayParameterConversion($params, $types)) {
                    [$sql, $params, $types] = $this->expandArrayParameters($sql, $params, $types);
                }

                $stmt = $connection->prepare($sql);
                if (count($types) > 0) {
                    $this->_bindTypedValues($stmt, $params, $types);
                    $result = $stmt->execute();
                } else {
                    $result = $stmt->execute($params);
                }
            } else {
                $result = $connection->query($sql);
            }

            return new Result($result, $this);
        } catch (Driver\Exception $e) {
            throw $this->convertExceptionDuringQuery($e, $sql, $params, $types);
        }
    }

    /**
     * Executes a caching query.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @throws CacheException
     * @throws Exception
     */
    public function executeCacheQuery(string $sql, array $params, array $types, QueryCacheProfile $qcp): Result
    {
        $resultCache = $qcp->getResultCache() ?? $this->_config->getResultCache();

        if ($resultCache === null) {
            throw NoResultDriverConfigured::new();
        }

        $connectionParams = $this->params;
        unset($connectionParams['platform']);

        [$cacheKey, $realKey] = $qcp->generateCacheKeys($sql, $params, $types, $connectionParams);

        $item = $resultCache->getItem($cacheKey);

        if ($item->isHit()) {
            $value = $item->get();
            if (isset($value[$realKey])) {
                return new Result(new ArrayResult($value[$realKey]), $this);
            }
        } else {
            $value = [];
        }

        $data = $this->fetchAllAssociative($sql, $params, $types);

        $value[$realKey] = $data;

        $item->set($value);

        $lifetime = $qcp->getLifetime();
        if ($lifetime > 0) {
            $item->expiresAfter($lifetime);
        }

        $resultCache->save($item);

        return new Result(new ArrayResult($data), $this);
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param list<mixed>|array<string, mixed>                                     $params Statement parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @throws Exception
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $connection = $this->connect();

        try {
            if (count($params) > 0) {
                if ($this->needsArrayParameterConversion($params, $types)) {
                    [$sql, $params, $types] = $this->expandArrayParameters($sql, $params, $types);
                }

                $stmt = $connection->prepare($sql);

                if (count($types) > 0) {
                    $this->_bindTypedValues($stmt, $params, $types);

                    $result = $stmt->execute();
                } else {
                    $result = $stmt->execute($params);
                }

                return $result->rowCount();
            }

            return $connection->exec($sql);
        } catch (Driver\Exception $e) {
            throw $this->convertExceptionDuringQuery($e, $sql, $params, $types);
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
     * Returns the ID of the last inserted row.
     *
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @return int|string The last insert ID, as an integer or a numeric string.
     *
     * @throws Exception
     */
    public function lastInsertId(): int|string
    {
        try {
            return $this->connect()->lastInsertId();
        } catch (Driver\Exception $e) {
            throw $this->convertException($e);
        }
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
    public function transactional(Closure $func): mixed
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
     * @throws Exception
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
     * Returns the savepoint name to use for nested transactions.
     */
    protected function _getNestedTransactionSavePointName(): string
    {
        return 'DOCTRINE2_SAVEPOINT_' . $this->transactionNestingLevel;
    }

    /**
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $connection = $this->connect();

        ++$this->transactionNestingLevel;

        if ($this->transactionNestingLevel === 1) {
            $connection->beginTransaction();
        } elseif ($this->nestTransactionsWithSavepoints) {
            $this->createSavepoint($this->_getNestedTransactionSavePointName());
        }

        $this->getEventManager()->dispatchEvent(Events::onTransactionBegin, new TransactionBeginEventArgs($this));
    }

    /**
     * @throws Exception
     */
    public function commit(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        if ($this->isRollbackOnly) {
            throw CommitFailedRollbackOnly::new();
        }

        $connection = $this->connect();

        if ($this->transactionNestingLevel === 1) {
            try {
                $connection->commit();
            } catch (Driver\Exception $e) {
                throw $this->convertException($e);
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $this->releaseSavepoint($this->_getNestedTransactionSavePointName());
        }

        --$this->transactionNestingLevel;

        $this->getEventManager()->dispatchEvent(Events::onTransactionCommit, new TransactionCommitEventArgs($this));

        if ($this->autoCommit !== false || $this->transactionNestingLevel !== 0) {
            return;
        }

        $this->beginTransaction();
    }

    /**
     * Commits all current nesting transactions.
     *
     * @throws Exception
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
     * @throws Exception
     */
    public function rollBack(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw NoActiveTransaction::new();
        }

        $connection = $this->connect();

        if ($this->transactionNestingLevel === 1) {
            $this->transactionNestingLevel = 0;

            try {
                $connection->rollBack();
            } catch (Driver\Exception $e) {
                throw $this->convertException($e);
            } finally {
                $this->isRollbackOnly = false;

                if ($this->autoCommit === false) {
                    $this->beginTransaction();
                }
            }
        } elseif ($this->nestTransactionsWithSavepoints) {
            $this->rollbackSavepoint($this->_getNestedTransactionSavePointName());
            --$this->transactionNestingLevel;
        } else {
            $this->isRollbackOnly = true;
            --$this->transactionNestingLevel;
        }

        $this->getEventManager()->dispatchEvent(Events::onTransactionRollBack, new TransactionRollBackEventArgs($this));
    }

    /**
     * Creates a new savepoint.
     *
     * @param string $savepoint The name of the savepoint to create.
     *
     * @throws Exception
     */
    public function createSavepoint(string $savepoint): void
    {
        $platform = $this->getDatabasePlatform();

        if (! $platform->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        $this->executeStatement($platform->createSavePoint($savepoint));
    }

    /**
     * Releases the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to release.
     *
     * @throws Exception
     */
    public function releaseSavepoint(string $savepoint): void
    {
        $platform = $this->getDatabasePlatform();

        if (! $platform->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        if (! $platform->supportsReleaseSavepoints()) {
            return;
        }

        $this->executeStatement($platform->releaseSavePoint($savepoint));
    }

    /**
     * Rolls back to the given savepoint.
     *
     * @param string $savepoint The name of the savepoint to rollback to.
     *
     * @throws Exception
     */
    public function rollbackSavepoint(string $savepoint): void
    {
        $platform = $this->getDatabasePlatform();

        if (! $platform->supportsSavepoints()) {
            throw SavepointsNotSupported::new();
        }

        $this->executeStatement($platform->rollbackSavePoint($savepoint));
    }

    /**
     * Provides access to the native database connection.
     *
     * @return resource|object
     *
     * @throws Exception
     */
    public function getNativeConnection()
    {
        return $this->connect()->getNativeConnection();
    }

    /**
     * Creates a SchemaManager that can be used to inspect or change the
     * database schema through the connection.
     *
     * @throws Exception
     */
    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->_driver->getSchemaManager(
            $this,
            $this->getDatabasePlatform()
        );
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
     *
     * @throws Exception
     */
    public function convertToDatabaseValue(mixed $value, string $type): mixed
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
     *
     * @throws Exception
     */
    public function convertToPHPValue(mixed $value, string $type): mixed
    {
        return Type::getType($type)->convertToPHPValue($value, $this->getDatabasePlatform());
    }

    /**
     * Binds a set of parameters, some or all of which are typed with a PDO binding type
     * or DBAL mapping type, to a given statement.
     *
     * @param DriverStatement                                                      $stmt   Prepared statement
     * @param list<mixed>|array<string, mixed>                                     $params Statement parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @throws Exception
     */
    private function _bindTypedValues(DriverStatement $stmt, array $params, array $types): void
    {
        // Check whether parameters are positional or named. Mixing is not allowed.
        if (is_int(key($params))) {
            $bindIndex = 1;

            foreach ($params as $key => $value) {
                if (isset($types[$key])) {
                    $type                  = $types[$key];
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
     * Gets the binding type of a given type.
     *
     * @param mixed                $value The value to bind.
     * @param int|string|Type|null $type  The type to bind (PDO or DBAL).
     *
     * @return array{mixed, int} [0] => the (escaped) value, [1] => the binding type.
     *
     * @throws Exception
     */
    private function getBindingInfo(mixed $value, int|string|Type|null $type): array
    {
        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value       = $type->convertToDatabaseValue($value, $this->getDatabasePlatform());
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type ?? ParameterType::STRING;
        }

        return [$value, $bindingType];
    }

    /**
     * Creates a new instance of a SQL query builder.
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new Query\QueryBuilder($this);
    }

    /**
     * @internal
     *
     * @param list<mixed>|array<string,mixed>                                      $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     */
    final public function convertExceptionDuringQuery(
        Driver\Exception $e,
        string $sql,
        array $params = [],
        array $types = []
    ): DriverException {
        return $this->handleDriverException($e, new Query($sql, $params, $types));
    }

    /**
     * @internal
     */
    final public function convertException(Driver\Exception $e): DriverException
    {
        return $this->handleDriverException($e, null);
    }

    /**
     * @param array<int, mixed>|array<string, mixed>                               $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     *
     * @return array{string, list<mixed>, array<int,Type|int|string|null>}
     */
    private function expandArrayParameters(string $sql, array $params, array $types): array
    {
        if ($this->parser === null) {
            $this->parser = $this->getDatabasePlatform()->createSQLParser();
        }

        $visitor = new ExpandArrayParameters($params, $types);

        $this->parser->parse($sql, $visitor);

        return [
            $visitor->getSQL(),
            $visitor->getParameters(),
            $visitor->getTypes(),
        ];
    }

    /**
     * @param array<int, mixed>|array<string, mixed>                               $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     */
    private function needsArrayParameterConversion(array $params, array $types): bool
    {
        if (is_string(key($params))) {
            return true;
        }

        foreach ($types as $type) {
            if (
                $type === self::PARAM_INT_ARRAY
                || $type === self::PARAM_STR_ARRAY
                || $type === self::PARAM_ASCII_STR_ARRAY
            ) {
                return true;
            }
        }

        return false;
    }

    private function handleDriverException(
        Driver\Exception $driverException,
        ?Query $query
    ): DriverException {
        if ($this->exceptionConverter === null) {
            $this->exceptionConverter = $this->_driver->getExceptionConverter();
        }

        $exception = $this->exceptionConverter->convert($driverException, $query);

        if ($exception instanceof ConnectionLost) {
            $this->close();
        }

        return $exception;
    }

    /**
     * BC layer for a wide-spread use-case of old DBAL APIs
     *
     * @deprecated This API is deprecated and will be removed after 2022
     *
     * @param array<int, mixed>|array<string, mixed>                               $params Query parameters
     * @param array<int, Type|int|string|null>|array<string, Type|int|string|null> $types  Parameter types
     *
     * @throws Exception
     */
    public function executeUpdate(string $sql, array $params = [], array $types = []): int|string
    {
        return $this->executeStatement($sql, $params, $types);
    }

    /**
     * BC layer for a wide-spread use-case of old DBAL APIs
     *
     * @deprecated This API is deprecated and will be removed after 2022
     */
    public function query(string $sql): Result
    {
        return $this->executeQuery($sql);
    }

    /**
     * BC layer for a wide-spread use-case of old DBAL APIs
     *
     * @deprecated This API is deprecated and will be removed after 2022
     */
    public function exec(string $sql): int|string
    {
        return $this->executeStatement($sql);
    }
}

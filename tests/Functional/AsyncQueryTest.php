<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Async\AsyncQuery;
use Doctrine\DBAL\Async\Exception\AsyncNotSupported;
use Doctrine\DBAL\Driver\Mysqli\Connection as MysqliConnection;
use Doctrine\DBAL\Driver\PDO\Connection as PDOConnection;
use Doctrine\DBAL\Driver\PgSQL\Connection as PgSQLConnection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function get_class;
use function is_resource;
use function microtime;

use const PHP_VERSION_ID;

/**
 * Functional tests for async query execution.
 *
 * @requires PHP >= 8.1
 */
class AsyncQueryTest extends FunctionalTestCase
{
    private const TABLE = 'async_test';

    protected function setUp(): void
    {
        parent::setUp();

        if (PHP_VERSION_ID < 80100) {
            self::markTestSkipped('Async queries require PHP 8.1 or higher');
        }
    }

    protected function tearDown(): void
    {
        $this->dropTableIfExists(self::TABLE);
        $this->markConnectionNotReusable();
    }

    public function testAsyncQueriesNotSupportedWithPDO(): void
    {
        $nativeConnection = $this->connection->getNativeConnection();

        if (! $nativeConnection instanceof \PDO) {
            self::markTestSkipped('This test requires a PDO connection');
        }

        $this->expectException(AsyncNotSupported::class);
        $this->expectExceptionMessage('does not support async queries');

        $this->connection->executeQueriesAsync([
            new AsyncQuery('SELECT 1'),
        ]);
    }

    public function testAsyncQueriesNotAllowedInTransaction(): void
    {
        $this->skipIfNotAsyncCapable();

        $this->connection->beginTransaction();

        try {
            $this->expectException(AsyncNotSupported::class);
            $this->expectExceptionMessage('transaction');

            $this->connection->executeQueriesAsync([
                new AsyncQuery('SELECT 1'),
            ]);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testEmptyQueryBatchThrowsException(): void
    {
        $this->skipIfNotAsyncCapable();

        $this->expectException(AsyncNotSupported::class);
        $this->expectExceptionMessage('empty batch');

        $this->connection->executeQueriesAsync([]);
    }

    public function testSingleAsyncQuery(): void
    {
        $this->skipIfNotAsyncCapable();

        $results = $this->connection->executeQueriesAsync([
            new AsyncQuery('SELECT 1 AS val'),
        ]);

        self::assertCount(1, $results);

        $row = $results[0]->fetchAssociative();
        self::assertIsArray($row);
        self::assertEquals(1, $row['val']);
    }

    public function testMultipleAsyncQueries(): void
    {
        $this->skipIfNotAsyncCapable();
        $this->createTestTable();

        // Insert test data
        $this->connection->insert(self::TABLE, ['name' => 'Alice', 'value' => 100]);
        $this->connection->insert(self::TABLE, ['name' => 'Bob', 'value' => 200]);
        $this->connection->insert(self::TABLE, ['name' => 'Charlie', 'value' => 300]);

        // Run multiple queries in parallel
        $results = $this->connection->executeQueriesAsync([
            new AsyncQuery('SELECT name FROM ' . self::TABLE . ' WHERE value = 100'),
            new AsyncQuery('SELECT name FROM ' . self::TABLE . ' WHERE value = 200'),
            new AsyncQuery('SELECT COUNT(*) as cnt FROM ' . self::TABLE),
        ]);

        self::assertCount(3, $results);

        // Verify first query result
        $row1 = $results[0]->fetchAssociative();
        self::assertIsArray($row1);
        self::assertEquals('Alice', $row1['name']);

        // Verify second query result
        $row2 = $results[1]->fetchAssociative();
        self::assertIsArray($row2);
        self::assertEquals('Bob', $row2['name']);

        // Verify third query result (count)
        $row3 = $results[2]->fetchAssociative();
        self::assertIsArray($row3);
        self::assertEquals(3, $row3['cnt']);
    }

    public function testAsyncQueryWithParameters(): void
    {
        $this->skipIfNotAsyncCapable();
        $this->createTestTable();

        // Insert test data
        $this->connection->insert(self::TABLE, ['name' => 'Test', 'value' => 42]);

        // For PostgreSQL, we can use positional parameters ($1, $2)
        // For MySQL, we need to embed values (async doesn't support prepared statements)
        $driverConnection = $this->getDriverConnection();

        if ($driverConnection instanceof PgSQLConnection) {
            $results = $this->connection->executeQueriesAsync([
                new AsyncQuery('SELECT name FROM ' . self::TABLE . ' WHERE value = $1', [42]),
            ]);
        } else {
            // For mysqli, use direct value (params are embedded in SQL)
            $results = $this->connection->executeQueriesAsync([
                new AsyncQuery('SELECT name FROM ' . self::TABLE . ' WHERE value = 42'),
            ]);
        }

        self::assertCount(1, $results);

        $row = $results[0]->fetchAssociative();
        self::assertIsArray($row);
        self::assertEquals('Test', $row['name']);
    }

    public function testAsyncQueriesPreserveOrder(): void
    {
        $this->skipIfNotAsyncCapable();

        // Execute queries that might complete in different order
        $results = $this->connection->executeQueriesAsync([
            new AsyncQuery('SELECT 1 AS order_num'),
            new AsyncQuery('SELECT 2 AS order_num'),
            new AsyncQuery('SELECT 3 AS order_num'),
        ]);

        self::assertCount(3, $results);

        // Results should be in the same order as queries
        self::assertEquals(1, $results[0]->fetchAssociative()['order_num']);
        self::assertEquals(2, $results[1]->fetchAssociative()['order_num']);
        self::assertEquals(3, $results[2]->fetchAssociative()['order_num']);
    }

    /**
     * Tests that queries actually run in parallel by using sleep functions.
     *
     * If queries run sequentially: 3 queries × 1 second = ~3 seconds
     * If queries run in parallel: ~1 second (+ overhead)
     *
     * We assert that total time is between 1 and 2.5 seconds to prove parallelism.
     */
    public function testQueriesExecuteInParallel(): void
    {
        $this->skipIfNotAsyncCapable();

        $sleepQuery = $this->getSleepQuery(1);

        $startTime = microtime(true);

        $results = $this->connection->executeQueriesAsync([
            new AsyncQuery($sleepQuery),
            new AsyncQuery($sleepQuery),
            new AsyncQuery($sleepQuery),
        ]);

        $endTime  = microtime(true);
        $duration = $endTime - $startTime;

        self::assertCount(3, $results);

        // If parallel: should take ~1 second (+ connection overhead)
        // If sequential: would take ~3 seconds
        // We allow up to 2.5 seconds to account for connection setup overhead
        self::assertGreaterThanOrEqual(1.0, $duration, 'Queries should take at least 1 second (sleep time)');
        self::assertLessThan(2.5, $duration, 'Queries should complete in under 2.5 seconds if running in parallel (sequential would take ~3s)');
    }

    /**
     * Tests parallel execution with varying sleep times.
     *
     * Total time should be approximately equal to the longest query,
     * not the sum of all query times.
     */
    public function testParallelExecutionWithVaryingSleepTimes(): void
    {
        $this->skipIfNotAsyncCapable();

        $startTime = microtime(true);

        // Queries with different sleep times: 0.5s, 1s, 0.3s
        $results = $this->connection->executeQueriesAsync([
            new AsyncQuery($this->getSleepQuery(0.5)),
            new AsyncQuery($this->getSleepQuery(1)),    // Longest query
            new AsyncQuery($this->getSleepQuery(0.3)),
        ]);

        $endTime  = microtime(true);
        $duration = $endTime - $startTime;

        self::assertCount(3, $results);

        // If parallel: should take ~1 second (the longest query)
        // If sequential: would take ~1.8 seconds (0.5 + 1 + 0.3)
        self::assertGreaterThanOrEqual(1.0, $duration, 'Should take at least as long as the longest query');
        self::assertLessThan(1.7, $duration, 'Should complete faster than sequential execution (which would take ~1.8s)');
    }

    /**
     * Gets a sleep query appropriate for the current database.
     *
     * @param float $seconds Number of seconds to sleep
     */
    private function getSleepQuery(float $seconds): string
    {
        $nativeConnection = $this->connection->getNativeConnection();

        if ($nativeConnection instanceof \mysqli) {
            // MySQL SLEEP() returns 0 on success
            return "SELECT SLEEP($seconds) AS slept";
        }

        // PostgreSQL pg_sleep() returns void, so we select 1 after
        return "SELECT pg_sleep($seconds), 1 AS slept";
    }

    private function createTestTable(): void
    {
        $table = new Table(self::TABLE);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addColumn('value', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    /**
     * Gets the underlying driver connection.
     *
     * @return MysqliConnection|PgSQLConnection|PDOConnection|object
     */
    private function getDriverConnection(): object
    {
        return $this->connection->getNativeConnection();
    }

    /**
     * Skips the test if the current connection does not support async queries.
     */
    private function skipIfNotAsyncCapable(): void
    {
        $nativeConnection = $this->connection->getNativeConnection();

        $isAsyncCapable = $nativeConnection instanceof \mysqli
            || $nativeConnection instanceof \PgSql\Connection
            || is_resource($nativeConnection);

        if (! $isAsyncCapable) {
            self::markTestSkipped(
                'This test requires an async-capable driver (pgsql or mysqli). '
                . 'Current driver connection: ' . get_class($nativeConnection)
            );
        }
    }
}


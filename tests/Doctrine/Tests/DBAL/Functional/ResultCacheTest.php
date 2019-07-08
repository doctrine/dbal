<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use const CASE_LOWER;
use function array_change_key_case;
use function array_merge;
use function array_shift;
use function array_values;
use function is_array;

/**
 * @group DDC-217
 */
class ResultCacheTest extends DbalFunctionalTestCase
{
    /** @var array<int, array<int, int|string>> */
    private $expectedResult = [['test_int' => 100, 'test_string' => 'foo'], ['test_int' => 200, 'test_string' => 'bar'], ['test_int' => 300, 'test_string' => 'baz']];

    /** @var DebugStack */
    private $sqlLogger;

    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('caching');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        foreach ($this->expectedResult as $row) {
            $this->connection->insert('caching', $row);
        }

        $config                                = $this->connection->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new DebugStack());

        $cache = new ArrayCache();
        $config->setResultCacheImpl($cache);
    }

    protected function tearDown() : void
    {
        $this->connection->getSchemaManager()->dropTable('caching');

        parent::tearDown();
    }

    public function testCacheFetchAssoc() : void
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $this->expectedResult,
            FetchMode::ASSOCIATIVE
        );
    }

    public function testFetchNum() : void
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_values($v);
        }

        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::NUMERIC);
    }

    public function testFetchBoth() : void
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_merge($v, array_values($v));
        }

        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::MIXED);
    }

    public function testFetchColumn() : void
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_shift($v);
        }

        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::COLUMN);
    }

    public function testMixingFetch() : void
    {
        $numExpectedResult = [];
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $data = $this->hydrateStmt($stmt, FetchMode::ASSOCIATIVE);

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $data = $this->hydrateStmt($stmt, FetchMode::NUMERIC);

        self::assertEquals($numExpectedResult, $data);
    }

    public function testIteratorFetch() : void
    {
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::MIXED);
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::ASSOCIATIVE);
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::NUMERIC);
    }

    private function assertStandardAndIteratorFetchAreEqual(int $fetchMode) : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));
        $data = $this->hydrateStmt($stmt, $fetchMode);

        $stmt          = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));
        $data_iterator = $this->hydrateStmtIterator($stmt, $fetchMode);

        self::assertEquals($data, $data_iterator);
    }

    public function testDontCloseNoCache() : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $data = [];

        while ($row = $stmt->fetch(FetchMode::ASSOCIATIVE)) {
            $data[] = $row;
        }

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $data = [];

        while ($row = $stmt->fetch(FetchMode::NUMERIC)) {
            $data[] = $row;
        }

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache() : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $stmt->fetch(FetchMode::ASSOCIATIVE);
        $stmt->closeCursor();

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        $this->hydrateStmt($stmt, FetchMode::NUMERIC);

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testFetchAllAndFinishSavesCache() : void
    {
        $layerCache = new ArrayCache();
        $stmt       = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(10, 'testcachekey', $layerCache));
        $stmt->fetchAll();
        $stmt->closeCursor();

        self::assertCount(1, $layerCache->fetch('testcachekey'));
    }

    public function testFetchAllColumn() : void
    {
        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL('1');

        $qcp = new QueryCacheProfile(0, 0, new ArrayCache());

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);
        $stmt->fetchAll(FetchMode::COLUMN);
        $stmt->closeCursor();

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);

        self::assertEquals([1], $stmt->fetchAll(FetchMode::COLUMN));
    }

    /**
     * @param array<int, array<int, int|string>> $expectedResult
     */
    private function assertCacheNonCacheSelectSameFetchModeAreEqual(array $expectedResult, int $fetchMode) : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(10, 'testcachekey'));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);
        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testEmptyResultCache() : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(10, 'emptycachekey'));
        $data = $this->hydrateStmt($stmt);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(10, 'emptycachekey'));
        $data = $this->hydrateStmt($stmt);

        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testChangeCacheImpl() : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(10, 'emptycachekey'));
        $data = $this->hydrateStmt($stmt);

        $secondCache = new ArrayCache();
        $stmt        = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(10, 'emptycachekey', $secondCache));
        $data        = $this->hydrateStmt($stmt);

        self::assertCount(2, $this->sqlLogger->queries, 'two hits');
        self::assertCount(1, $secondCache->fetch('emptycachekey'));
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateStmt(ResultStatement $stmt, int $fetchMode = FetchMode::ASSOCIATIVE) : array
    {
        $data = [];
        while ($row = $stmt->fetch($fetchMode)) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateStmtIterator(ResultStatement $stmt, int $fetchMode = FetchMode::ASSOCIATIVE) : array
    {
        $data = [];
        $stmt->setFetchMode($fetchMode);
        foreach ($stmt as $row) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();

        return $data;
    }
}

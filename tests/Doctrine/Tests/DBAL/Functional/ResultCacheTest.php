<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use PDO;

/**
 * @group DDC-217
 */
class ResultCacheTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private $expectedResult = [['test_int' => 100, 'test_string' => 'foo'], ['test_int' => 200, 'test_string' => 'bar'], ['test_int' => 300, 'test_string' => 'baz']];
    private $sqlLogger;

    protected function setUp()
    {
        parent::setUp();

        $table = new \Doctrine\DBAL\Schema\Table("caching");
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->_conn->getSchemaManager();
        $sm->createTable($table);

        foreach ($this->expectedResult as $row) {
            $this->_conn->insert('caching', $row);
        }

        $config = $this->_conn->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new \Doctrine\DBAL\Logging\DebugStack);

        $cache = new \Doctrine\Common\Cache\ArrayCache;
        $config->setResultCacheImpl($cache);
    }

    protected function tearDown()
    {
        $this->_conn->getSchemaManager()->dropTable('caching');

        parent::tearDown();
    }

    public function testCacheFetchAssoc()
    {
        self::assertCacheNonCacheSelectSameFetchModeAreEqual($this->expectedResult, \PDO::FETCH_ASSOC);
    }

    public function testFetchNum()
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_values($v);
        }
        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_NUM);
    }

    public function testFetchBoth()
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_merge($v, array_values($v));
        }
        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_BOTH);
    }

    public function testFetchColumn()
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_shift($v);
        }
        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_COLUMN);
    }

    public function testMixingFetch()
    {
        $numExpectedResult = [];
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_ASSOC);

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_NUM);

        self::assertEquals($numExpectedResult, $data);
    }

    public function testIteratorFetch()
    {
        self::assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_BOTH);
        self::assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_ASSOC);
        self::assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_NUM);
    }

    public function assertStandardAndIteratorFetchAreEqual($fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));
        $data = $this->hydrateStmt($stmt, $fetchMode);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));
        $data_iterator = $this->hydrateStmtIterator($stmt, $fetchMode);

        self::assertEquals($data, $data_iterator);
    }

    public function testDontCloseNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $data = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $data = [];
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $data[] = $row;
        }

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_NUM);

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, $fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", [], [], new QueryCacheProfile(10, "testcachekey"));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);
        self::assertCount(1, $this->sqlLogger->queries, "just one dbal hit");
    }

    public function testEmptyResultCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", [], [], new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", [], [], new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        self::assertCount(1, $this->sqlLogger->queries, "just one dbal hit");
    }

    public function testChangeCacheImpl()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", [], [], new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $secondCache = new \Doctrine\Common\Cache\ArrayCache;
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", [], [], new QueryCacheProfile(10, "emptycachekey", $secondCache));
        $data = $this->hydrateStmt($stmt);

        self::assertCount(2, $this->sqlLogger->queries, "two hits");
        self::assertCount(1, $secondCache->fetch("emptycachekey"));
    }

    private function hydrateStmt($stmt, $fetchMode = \PDO::FETCH_ASSOC)
    {
        $data = [];
        while ($row = $stmt->fetch($fetchMode)) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();
        return $data;
    }

    private function hydrateStmtIterator($stmt, $fetchMode = \PDO::FETCH_ASSOC)
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

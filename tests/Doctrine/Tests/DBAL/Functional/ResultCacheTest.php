<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\FetchMode;

/**
 * @group DDC-217
 */
class ResultCacheTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private $expectedResult = array(array('test_int' => 100, 'test_string' => 'foo'), array('test_int' => 200, 'test_string' => 'bar'), array('test_int' => 300, 'test_string' => 'baz'));
    private $sqlLogger;

    protected function setUp()
    {
        parent::setUp();

        $table = new \Doctrine\DBAL\Schema\Table("caching");
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', array('notnull' => false));
        $table->setPrimaryKey(array('test_int'));

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
        self::assertCacheNonCacheSelectSameFetchModeAreEqual(
            $this->expectedResult,
            FetchMode::ASSOCIATIVE
        );
    }

    public function testFetchNum()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_values($v);
        }

        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::NUMERIC);
    }

    public function testFetchBoth()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_merge($v, array_values($v));
        }

        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::MIXED);
    }

    public function testFetchColumn()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_shift($v);
        }

        self::assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, FetchMode::COLUMN);
    }

    public function testMixingFetch()
    {
        $numExpectedResult = array();
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, FetchMode::ASSOCIATIVE);

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, FetchMode::NUMERIC);

        self::assertEquals($numExpectedResult, $data);
    }

    public function testIteratorFetch()
    {
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::MIXED);
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::ASSOCIATIVE);
        self::assertStandardAndIteratorFetchAreEqual(FetchMode::NUMERIC);
    }

    public function assertStandardAndIteratorFetchAreEqual($fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));
        $data = $this->hydrateStmt($stmt, $fetchMode);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));
        $data_iterator = $this->hydrateStmtIterator($stmt, $fetchMode);

        self::assertEquals($data, $data_iterator);
    }

    public function testDontCloseNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = array();

        while ($row = $stmt->fetch(FetchMode::ASSOCIATIVE)) {
            $data[] = $row;
        }

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = array();

        while ($row = $stmt->fetch(FetchMode::NUMERIC)) {
            $data[] = $row;
        }

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $stmt->fetch(FetchMode::ASSOCIATIVE);
        $stmt->closeCursor();

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $this->hydrateStmt($stmt, FetchMode::NUMERIC);

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, $fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);
        self::assertCount(1, $this->sqlLogger->queries, "just one dbal hit");
    }

    public function testEmptyResultCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        self::assertCount(1, $this->sqlLogger->queries, "just one dbal hit");
    }

    public function testChangeCacheImpl()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $secondCache = new \Doctrine\Common\Cache\ArrayCache;
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey", $secondCache));
        $data = $this->hydrateStmt($stmt);

        self::assertCount(2, $this->sqlLogger->queries, "two hits");
        self::assertCount(1, $secondCache->fetch("emptycachekey"));
    }

    private function hydrateStmt($stmt, $fetchMode = FetchMode::ASSOCIATIVE)
    {
        $data = array();
        while ($row = $stmt->fetch($fetchMode)) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();
        return $data;
    }

    private function hydrateStmtIterator($stmt, $fetchMode = FetchMode::ASSOCIATIVE)
    {
        $data = array();
        $stmt->setFetchMode($fetchMode);
        foreach ($stmt as $row) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();
        return $data;
    }
}

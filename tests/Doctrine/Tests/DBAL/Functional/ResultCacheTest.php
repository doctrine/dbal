<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

/**
 * @group DDC-217
 */
class ResultCacheTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private $expectedResult = array(array('test_int' => 100, 'test_string' => 'foo'), array('test_int' => 200, 'test_string' => 'bar'), array('test_int' => 300, 'test_string' => 'baz'));
    private $sqlLogger;

    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("caching");
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string', array('notnull' => false));
            $table->setPrimaryKey(array('test_int'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch(\Exception $e) {

        }
        $this->_conn->executeUpdate('DELETE FROM caching');
        foreach ($this->expectedResult as $row) {
            $this->_conn->insert('caching', $row);
        }

        $config = $this->_conn->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new \Doctrine\DBAL\Logging\DebugStack);

        $cache = new \Doctrine\Common\Cache\ArrayCache;
        $config->setResultCacheImpl($cache);
    }

    public function testCacheFetchAssoc()
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($this->expectedResult, \PDO::FETCH_ASSOC);
    }

    public function testFetchNum()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_values($v);
        }
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_NUM);
    }

    public function testFetchBoth()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_merge($v, array_values($v));
        }
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_BOTH);
    }
	
    public function testFetchColumn()
    {
        $expectedResult = array();
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_shift($v);
        }
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_COLUMN);
    }

    public function testMixingFetch()
    {
        $numExpectedResult = array();
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_ASSOC);

        $this->assertEquals($this->expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_NUM);

        $this->assertEquals($numExpectedResult, $data);
    }

    public function testIteratorFetch()
    {
        $this->assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_BOTH);
        $this->assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_ASSOC);
        $this->assertStandardAndIteratorFetchAreEqual(\PDO::FETCH_NUM);
    }

    public function assertStandardAndIteratorFetchAreEqual($fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));
        $data = $this->hydrateStmt($stmt, $fetchMode);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));
        $data_iterator = $this->hydrateStmtIterator($stmt, $fetchMode);

        $this->assertEquals($data, $data_iterator);
    }

    public function testDontCloseNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $data[] = $row;
        }

        $this->assertEquals(2, count($this->sqlLogger->queries));
    }

    public function testDontFinishNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $data = $this->hydrateStmt($stmt, \PDO::FETCH_NUM);

        $this->assertEquals(2, count($this->sqlLogger->queries));
    }

    public function assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, $fetchMode)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $this->assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        $this->assertEquals($expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching ORDER BY test_int ASC", array(), array(), new QueryCacheProfile(10, "testcachekey"));

        $this->assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateStmt($stmt, $fetchMode);
        $this->assertEquals($expectedResult, $data);
        $this->assertEquals(1, count($this->sqlLogger->queries), "just one dbal hit");
    }

    public function testEmptyResultCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $this->assertEquals(1, count($this->sqlLogger->queries), "just one dbal hit");
    }

    public function testChangeCacheImpl()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey"));
        $data = $this->hydrateStmt($stmt);

        $secondCache = new \Doctrine\Common\Cache\ArrayCache;
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching WHERE test_int > 500", array(), array(), new QueryCacheProfile(10, "emptycachekey", $secondCache));
        $data = $this->hydrateStmt($stmt);

        $this->assertEquals(2, count($this->sqlLogger->queries), "two hits");
        $this->assertEquals(1, count($secondCache->fetch("emptycachekey")));
    }

    private function hydrateStmt($stmt, $fetchMode = \PDO::FETCH_ASSOC)
    {
        $data = array();
        while ($row = $stmt->fetch($fetchMode)) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }
        $stmt->closeCursor();
        return $data;
    }

    private function hydrateStmtIterator($stmt, $fetchMode = \PDO::FETCH_ASSOC)
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

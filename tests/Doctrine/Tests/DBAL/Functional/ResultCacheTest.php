<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Types\Type;
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

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch(\Exception $e) {

        }
        $this->_conn->executeUpdate('DELETE FROM caching');
        foreach ($this->expectedResult AS $row) {
            $this->_conn->insert('caching', $row);
        }

        $config = $this->_conn->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new \Doctrine\DBAL\Logging\DebugStack);
        $config->setResultCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
    }

    public function testCacheFetchAssoc()
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($this->expectedResult, \PDO::FETCH_ASSOC);
    }

    public function testFetchNum()
    {
        $expectedResult = array();
        foreach ($this->expectedResult AS $v) {
            $expectedResult[] = array_values($v);
        }
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_NUM);
    }

    public function testFetchBoth()
    {
        $expectedResult = array();
        foreach ($this->expectedResult AS $v) {
            $expectedResult[] = array_merge($v, array_values($v));
        }
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, \PDO::FETCH_BOTH);
    }

    public function testMixingFetch()
    {
        $numExpectedResult = array();
        foreach ($this->expectedResult AS $v) {
            $numExpectedResult[] = array_values($v);
        }
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }
        $stmt->closeCursor();

        $this->assertEquals($this->expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $data[] = $row;
        }
        $stmt->closeCursor();

        $this->assertEquals($numExpectedResult, $data);
    }

    public function testDontCloseNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $data[] = $row;
        }

        $this->assertEquals(2, count($this->sqlLogger->queries));
    }

    public function testDontFinishNoCache()
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $data = array();
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $data[] = $row;
        }
        $stmt->closeCursor();

        $this->assertEquals(2, count($this->sqlLogger->queries));
    }

    public function assertCacheNonCacheSelectSameFetchModeAreEqual($expectedResult, $fetchStyle)
    {
        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $this->assertEquals(2, $stmt->columnCount());

        $data = array();
        while ($row = $stmt->fetch($fetchStyle)) {
            $data[] = $row;
        }
        $stmt->closeCursor();

        $this->assertEquals($expectedResult, $data);

        $stmt = $this->_conn->executeQuery("SELECT * FROM caching", array(), array(), "testcachekey", 10);

        $this->assertEquals(2, $stmt->columnCount());

        $data = array();
        while ($row = $stmt->fetch($fetchStyle)) {
            $data[] = $row;
        }
        $stmt->closeCursor();

        $this->assertEquals($expectedResult, $data);

        $this->assertEquals(1, count($this->sqlLogger->queries), "just one dbal hit");
    }
}
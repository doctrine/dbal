<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\DriverManager;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-20
 */
class MasterSlaveConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $platformName = $this->_conn->getDatabasePlatform()->getName();

        // This is a MySQL specific test, skip other vendors.
        if ($platformName != 'mysql') {
            $this->markTestSkipped(sprintf('Test does not work on %s.', $platformName));
        }

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("master_slave_table");
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(array('test_int'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);


        } catch(\Exception $e) {
        }

        $this->_conn->executeUpdate('DELETE FROM master_slave_table');
        $this->_conn->insert('master_slave_table', array('test_int' => 1));
    }

    public function createMasterSlaveConnection($keepSlave = false)
    {
        $params = $this->_conn->getParams();
        $params['master']       = $params;
        $params['slaves']       = array($params, $params);
        $params['keepSlave']    = $keepSlave;
        $params['wrapperClass'] = 'Doctrine\DBAL\Connections\MasterSlaveConnection';

        return DriverManager::getConnection($params);
    }

    public function testMasterOnConnect()
    {
        $conn = $this->createMasterSlaveConnection();

        self::assertFalse($conn->isConnectedToMaster());
        $conn->connect('slave');
        self::assertFalse($conn->isConnectedToMaster());
        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());
    }

    public function testNoMasterOnExecuteQuery()
    {
        $conn = $this->createMasterSlaveConnection();

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $data = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(1, $data[0]['num']);
        self::assertFalse($conn->isConnectedToMaster());
    }

    public function testMasterOnWriteOperation()
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->insert('master_slave_table', array('test_int' => 30));

        self::assertTrue($conn->isConnectedToMaster());

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $data = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(2, $data[0]['num']);
        self::assertTrue($conn->isConnectedToMaster());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepSlaveBeginTransactionStaysOnMaster()
    {
        $conn = $this->createMasterSlaveConnection($keepSlave = true);
        $conn->connect('slave');

        $conn->beginTransaction();
        $conn->insert('master_slave_table', array('test_int' => 30));
        $conn->commit();

        self::assertTrue($conn->isConnectedToMaster());

        $conn->connect();
        self::assertTrue($conn->isConnectedToMaster());

        $conn->connect('slave');
        self::assertFalse($conn->isConnectedToMaster());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepSlaveInsertStaysOnMaster()
    {
        $conn = $this->createMasterSlaveConnection($keepSlave = true);
        $conn->connect('slave');

        $conn->insert('master_slave_table', array('test_int' => 30));

        self::assertTrue($conn->isConnectedToMaster());

        $conn->connect();
        self::assertTrue($conn->isConnectedToMaster());

        $conn->connect('slave');
        self::assertFalse($conn->isConnectedToMaster());
    }

    public function testMasterSlaveConnectionCloseAndReconnect()
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());

        $conn->close();
        self::assertFalse($conn->isConnectedToMaster());

        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());
    }
}

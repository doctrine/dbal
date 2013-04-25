<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;

/**
 * @group DBAL-20
 */
class MasterSlaveConnectionTest extends DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        if ($this->_conn->getDatabasePlatform()->getName() == "sqlite") {
            $this->markTestSkipped('Test does not work on sqlite.');
        }

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("master_slave_table");
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(array('test_int'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);

            $table = new \Doctrine\DBAL\Schema\Table("master_slave_table2");
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(array('test_int'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch (\Exception $e) {

        }

        $this->_conn->executeUpdate('DELETE FROM master_slave_table');
        $this->_conn->insert('master_slave_table', array('test_int' => 1));

        $this->_conn->executeUpdate('DELETE FROM master_slave_table2');
        $this->_conn->insert('master_slave_table2', array('test_int' => 2));
        $this->_conn->insert('master_slave_table2', array('test_int' => 3));
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

        $this->assertFalse($conn->isConnectedToMaster());
        $conn->connect('slave');
        $this->assertFalse($conn->isConnectedToMaster());
        $conn->connect('master');
        $this->assertTrue($conn->isConnectedToMaster());
    }

    public function testNoMasterOnExecuteQuery()
    {
        $conn = $this->createMasterSlaveConnection();

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $data = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        $this->assertEquals(1, $data[0]['num']);
        $this->assertFalse($conn->isConnectedToMaster());
    }

    public function testNoMasterOnQuery()
    {
        $conn = $this->createMasterSlaveConnection();

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $statement = $conn->query($sql);
        $data = $statement->fetchAll();
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        $this->assertEquals(1, $data[0]['num']);
        $this->assertFalse($conn->isConnectedToMaster());
    }

    public function testNoMasterOnPrepare()
    {
        $conn = $this->createMasterSlaveConnection();

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $statement = $conn->prepare($sql);
        $statement->execute();
        $data = $statement->fetchAll();
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        $this->assertEquals(1, $data[0]['num']);
        $this->assertFalse($conn->isConnectedToMaster());
    }

    /**
     * @dataProvider sqlDataProvider
     */
    public function testMasterOrSlaveOnExecuteQuery($sql, $params, $types, $connectedToMaster)
    {
        $conn = $this->createMasterSlaveConnection();
        $this->assertFalse($conn->isConnectedToMaster());

        $conn->executeQuery($sql, $params, $types);

        $this->assertEquals($connectedToMaster, $conn->isConnectedToMaster());
    }

    /**
     * @dataProvider sqlDataProvider
     */
    public function testMasterOrSlaveOnPrepare($sql, $params, $types, $connectedToMaster)
    {
        $conn = $this->createMasterSlaveConnection();
        $this->assertFalse($conn->isConnectedToMaster());

        $statement = $conn->prepare($sql);

        foreach ($params as $key=>$param) {
            $statement->bindValue($key+1, $param, $types[$key]);
        }

        $this->assertEquals($connectedToMaster, $conn->isConnectedToMaster());
    }

    public function sqlDataProvider()
    {
        return array(
            array('INSERT INTO master_slave_table (test_int) VALUES (?)', array(4), array(Type::INTEGER), true),
            array('SELECT test_int FROM master_slave_table WHERE test_int=?', array(2), array(Type::INTEGER), false),
            array('select test_int from master_slave_table where test_int=?', array(2), array(Type::INTEGER), false),
            array('SELECT test_int AS select_data FROM master_slave_table WHERE test_int=?', array(2), array(Type::INTEGER), false),
            array('SELECT test_int AS insert_data FROM master_slave_table WHERE test_int=?', array(2), array(Type::INTEGER), false),
            array('SELECT test_int FROM master_slave_table WHERE test_int=(SELECT MIN(test_int) FROM master_slave_table2)', array(), array(), false),
            array('UPDATE master_slave_table SET test_int=(SELECT MIN(test_int) FROM master_slave_table2)', array(), array(), true),
            array('TRUNCATE TABLE master_slave_table2', array(), array(), true),
            array('INSERT INTO master_slave_table2 SELECT * FROM master_slave_table', array(), array(), true),
            array('UPDATE master_slave_table SET test_int=?', array(3), array(Type::INTEGER), true),
            array('update master_slave_table set test_int=?', array(3), array(Type::INTEGER), true),
            array('DELETE FROM master_slave_table WHERE test_int=?', array(3), array(Type::INTEGER), true),
        );
    }

    public function testMasterOnWriteOperation()
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->insert('master_slave_table', array('test_int' => 30));

        $this->assertTrue($conn->isConnectedToMaster());

        $sql = "SELECT count(*) as num FROM master_slave_table";
        $data = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        $this->assertEquals(2, $data[0]['num']);
        $this->assertTrue($conn->isConnectedToMaster());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepSlaveBeginTransactionStaysOnMaster()
    {
        $conn = $this->createMasterSlaveConnection($keepSlave = true);
        $conn->connect('slave');

        $conn->insert('master_slave_table', array('test_int' => 30));

        $this->assertTrue($conn->isConnectedToMaster());

        $conn->connect();
        $this->assertTrue($conn->isConnectedToMaster());

        $conn->connect('slave');
        $this->assertFalse($conn->isConnectedToMaster());
    }
}

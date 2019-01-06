<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;
use const CASE_LOWER;
use function array_change_key_case;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;

/**
 * @group DBAL-20
 */
class MasterSlaveConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $platformName = $this->connection->getDatabasePlatform()->getName();

        // This is a MySQL specific test, skip other vendors.
        if ($platformName !== 'mysql') {
            $this->markTestSkipped(sprintf('Test does not work on %s.', $platformName));
        }

        try {
            $table = new Table('master_slave_table');
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(['test_int']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);
        } catch (Throwable $e) {
        }

        $this->connection->executeUpdate('DELETE FROM master_slave_table');
        $this->connection->insert('master_slave_table', ['test_int' => 1]);
    }

    private function createMasterSlaveConnection(bool $keepSlave = false) : MasterSlaveConnection
    {
        return DriverManager::getConnection($this->createMasterSlaveConnectionParams($keepSlave));
    }

    /**
     * @return mixed[]
     */
    private function createMasterSlaveConnectionParams(bool $keepSlave = false) : array
    {
        $params                 = $this->connection->getParams();
        $params['master']       = $params;
        $params['slaves']       = [$params, $params];
        $params['keepSlave']    = $keepSlave;
        $params['wrapperClass'] = MasterSlaveConnection::class;

        return $params;
    }

    public function testInheritCharsetFromMaster() : void
    {
        $charsets = [
            'utf8',
            'latin1',
        ];

        foreach ($charsets as $charset) {
            $params                      = $this->createMasterSlaveConnectionParams();
            $params['master']['charset'] = $charset;

            foreach ($params['slaves'] as $index => $slaveParams) {
                if (! isset($slaveParams['charset'])) {
                    continue;
                }

                unset($params['slaves'][$index]['charset']);
            }

            /** @var MasterSlaveConnection $conn */
            $conn = DriverManager::getConnection($params);
            $conn->connect('slave');

            self::assertFalse($conn->isConnectedToMaster());

            $clientCharset = $conn->fetchColumn('select @@character_set_client as c');

            self::assertSame(
                $charset,
                substr(strtolower($clientCharset), 0, strlen($charset))
            );
        }
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

        $sql     = 'SELECT count(*) as num FROM master_slave_table';
        $data    = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(1, $data[0]['num']);
        self::assertFalse($conn->isConnectedToMaster());
    }

    public function testMasterOnWriteOperation()
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->insert('master_slave_table', ['test_int' => 30]);

        self::assertTrue($conn->isConnectedToMaster());

        $sql     = 'SELECT count(*) as num FROM master_slave_table';
        $data    = $conn->fetchAll($sql);
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
        $conn->insert('master_slave_table', ['test_int' => 30]);
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

        $conn->insert('master_slave_table', ['test_int' => 30]);

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

    public function testQueryOnMaster()
    {
        $conn = $this->createMasterSlaveConnection();

        $query = 'SELECT count(*) as num FROM master_slave_table';

        $statement = $conn->query($query);

        self::assertInstanceOf(Statement::class, $statement);

        //Query must be executed only on Master
        self::assertTrue($conn->isConnectedToMaster());

        $data = $statement->fetchAll();

        //Default fetchmode is FetchMode::ASSOCIATIVE
        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);
        self::assertEquals(1, $data[0]['num']);
    }

    public function testQueryOnSlave()
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->connect('slave');

        $query = 'SELECT count(*) as num FROM master_slave_table';

        $statement = $conn->query($query);

        self::assertInstanceOf(Statement::class, $statement);

        //Query must be executed only on Master, even when we connect to the slave
        self::assertTrue($conn->isConnectedToMaster());

        $data = $statement->fetchAll();

        //Default fetchmode is FetchMode::ASSOCIATIVE
        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);

        self::assertEquals(1, $data[0]['num']);
    }
}

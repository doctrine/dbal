<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Throwable;

use function array_change_key_case;
use function assert;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;

use const CASE_LOWER;

/**
 * @group DBAL-20
 */
class MasterSlaveConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $platformName = $this->connection->getDatabasePlatform()->getName();

        // This is a MySQL specific test, skip other vendors.
        if ($platformName !== 'mysql') {
            self::markTestSkipped(sprintf('Test does not work on %s.', $platformName));
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

    private function createMasterSlaveConnection(bool $keepSlave = false): MasterSlaveConnection
    {
        $connection = DriverManager::getConnection($this->createMasterSlaveConnectionParams($keepSlave));
        assert($connection instanceof MasterSlaveConnection);

        return $connection;
    }

    /**
     * @return mixed[]
     */
    private function createMasterSlaveConnectionParams(bool $keepSlave = false): array
    {
        $params                 = $this->connection->getParams();
        $params['master']       = $params;
        $params['slaves']       = [$params, $params];
        $params['keepSlave']    = $keepSlave;
        $params['wrapperClass'] = MasterSlaveConnection::class;

        return $params;
    }

    public function testInheritCharsetFromMaster(): void
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

            $conn = DriverManager::getConnection($params);
            self::assertInstanceOf(MasterSlaveConnection::class, $conn);
            $conn->connect('slave');

            self::assertFalse($conn->isConnectedToMaster());

            $clientCharset = $conn->fetchOne('select @@character_set_client as c');

            self::assertSame(
                $charset,
                substr(strtolower($clientCharset), 0, strlen($charset))
            );
        }
    }

    public function testMasterOnConnect(): void
    {
        $conn = $this->createMasterSlaveConnection();

        self::assertFalse($conn->isConnectedToMaster());
        $conn->connect('slave');
        self::assertFalse($conn->isConnectedToMaster());
        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());
    }

    public function testNoMasterOnExecuteQuery(): void
    {
        $conn = $this->createMasterSlaveConnection();

        $sql     = 'SELECT count(*) as num FROM master_slave_table';
        $data    = $conn->fetchAllAssociative($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(1, $data[0]['num']);
        self::assertFalse($conn->isConnectedToMaster());
    }

    public function testMasterOnWriteOperation(): void
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->insert('master_slave_table', ['test_int' => 30]);

        self::assertTrue($conn->isConnectedToMaster());

        $sql     = 'SELECT count(*) as num FROM master_slave_table';
        $data    = $conn->fetchAllAssociative($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(2, $data[0]['num']);
        self::assertTrue($conn->isConnectedToMaster());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepSlaveBeginTransactionStaysOnMaster(): void
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
    public function testKeepSlaveInsertStaysOnMaster(): void
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

    public function testMasterSlaveConnectionCloseAndReconnect(): void
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());

        $conn->close();
        self::assertFalse($conn->isConnectedToMaster());

        $conn->connect('master');
        self::assertTrue($conn->isConnectedToMaster());
    }

    public function testQueryOnMaster(): void
    {
        $conn = $this->createMasterSlaveConnection();

        $query = 'SELECT count(*) as num FROM master_slave_table';

        $result = $conn->query($query);

        //Query must be executed only on Master
        self::assertTrue($conn->isConnectedToMaster());

        $data = $result->fetchAllAssociative();

        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);
        self::assertEquals(1, $data[0]['num']);
    }

    public function testQueryOnSlave(): void
    {
        $conn = $this->createMasterSlaveConnection();
        $conn->connect('slave');

        $query = 'SELECT count(*) as num FROM master_slave_table';

        $result = $conn->query($query);

        //Query must be executed only on Master, even when we connect to the slave
        self::assertTrue($conn->isConnectedToMaster());

        $data = $result->fetchAllAssociative();

        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);

        self::assertEquals(1, $data[0]['num']);
    }
}

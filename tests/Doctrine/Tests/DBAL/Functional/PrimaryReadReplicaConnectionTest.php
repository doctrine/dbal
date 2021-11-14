<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;

use function array_change_key_case;
use function sprintf;

use const CASE_LOWER;

/**
 * @group DBAL-20
 * @psalm-import-type Params from DriverManager
 */
class PrimaryReadReplicaConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $platformName = $this->connection->getDatabasePlatform()->getName();

        // This is a MySQL specific test, skip other vendors.
        if ($platformName !== 'mysql') {
            $this->markTestSkipped(sprintf('Test does not work on %s.', $platformName));
        }

        try {
            $table = new Table('primary_replica_table');
            $table->addColumn('test_int', 'integer');
            $table->setPrimaryKey(['test_int']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);
        } catch (Throwable $e) {
        }

        $this->connection->executeStatement('DELETE FROM primary_replica_table');
        $this->connection->insert('primary_replica_table', ['test_int' => 1]);
    }

    private function createPrimaryReadReplicaConnection(bool $keepReplica = false): PrimaryReadReplicaConnection
    {
        $connection = DriverManager::getConnection($this->createPrimaryReadReplicaConnectionParams($keepReplica));

        self::assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);

        return $connection;
    }

    /**
     * @return mixed[]
     * @psalm-return Params
     */
    private function createPrimaryReadReplicaConnectionParams(bool $keepReplica = false): array
    {
        $params                 = $this->connection->getParams();
        $params['primary']      = $params;
        $params['replica']      = [$params, $params];
        $params['keepReplica']  = $keepReplica;
        $params['wrapperClass'] = PrimaryReadReplicaConnection::class;

        return $params;
    }

    public function testInheritCharsetFromPrimary(): void
    {
        $charsets = [
            'utf8mb4',
            'latin1',
        ];

        foreach ($charsets as $charset) {
            $params                       = $this->createPrimaryReadReplicaConnectionParams();
            $params['primary']['charset'] = $charset;

            self::assertArrayHasKey('replica', $params);

            foreach ($params['replica'] as $index => $replicaParams) {
                if (! isset($replicaParams['charset'])) {
                    continue;
                }

                unset($params['replica'][$index]['charset']);
            }

            $conn = DriverManager::getConnection($params);
            self::assertInstanceOf(PrimaryReadReplicaConnection::class, $conn);
            $conn->ensureConnectedToReplica();

            self::assertFalse($conn->isConnectedToPrimary());

            $clientCharset = $conn->fetchColumn('select @@character_set_client as c');

            self::assertSame($charset, $clientCharset);
        }
    }

    public function testPrimaryOnConnect(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();

        self::assertFalse($conn->isConnectedToPrimary());
        $conn->ensureConnectedToReplica();
        self::assertFalse($conn->isConnectedToPrimary());
        $conn->ensureConnectedToPrimary();
        self::assertTrue($conn->isConnectedToPrimary());
    }

    public function testNoPrimaryrOnExecuteQuery(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();

        $sql     = 'SELECT count(*) as num FROM primary_replica_table';
        $data    = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(1, $data[0]['num']);
        self::assertFalse($conn->isConnectedToPrimary());
    }

    public function testPrimaryOnWriteOperation(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();
        $conn->insert('primary_replica_table', ['test_int' => 30]);

        self::assertTrue($conn->isConnectedToPrimary());

        $sql     = 'SELECT count(*) as num FROM primary_replica_table';
        $data    = $conn->fetchAll($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(2, $data[0]['num']);
        self::assertTrue($conn->isConnectedToPrimary());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepReplicaBeginTransactionStaysOnPrimary(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection($keepReplica = true);
        $conn->ensureConnectedToReplica();

        $conn->beginTransaction();
        $conn->insert('primary_replica_table', ['test_int' => 30]);
        $conn->commit();

        self::assertTrue($conn->isConnectedToPrimary());

        $conn->connect();
        self::assertTrue($conn->isConnectedToPrimary());

        $conn->ensureConnectedToReplica();
        self::assertFalse($conn->isConnectedToPrimary());
    }

    /**
     * @group DBAL-335
     */
    public function testKeepReplicaInsertStaysOnPrimary(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection($keepReplica = true);
        $conn->ensureConnectedToReplica();

        $conn->insert('primary_replica_table', ['test_int' => 30]);

        self::assertTrue($conn->isConnectedToPrimary());

        $conn->connect();
        self::assertTrue($conn->isConnectedToPrimary());

        $conn->ensureConnectedToReplica();
        self::assertFalse($conn->isConnectedToPrimary());
    }

    public function testPrimaryReadReplicaConnectionCloseAndReconnect(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();
        $conn->ensureConnectedToPrimary();
        self::assertTrue($conn->isConnectedToPrimary());

        $conn->close();
        self::assertFalse($conn->isConnectedToPrimary());

        $conn->ensureConnectedToPrimary();
        self::assertTrue($conn->isConnectedToPrimary());
    }

    public function testQueryOnPrimary(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();

        $query = 'SELECT count(*) as num FROM primary_replica_table';

        $statement = $conn->query($query);

        self::assertInstanceOf(Statement::class, $statement);

        //Query must be executed only on Primary
        self::assertTrue($conn->isConnectedToPrimary());

        $data = $statement->fetchAll();

        //Default fetchmode is FetchMode::ASSOCIATIVE
        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);
        self::assertEquals(1, $data[0]['num']);
    }

    public function testQueryOnReplica(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection();
        $conn->ensureConnectedToReplica();

        $query = 'SELECT count(*) as num FROM primary_replica_table';

        $statement = $conn->query($query);

        self::assertInstanceOf(Statement::class, $statement);

        //Query must be executed only on Primary, even when we connect to the replica
        self::assertTrue($conn->isConnectedToPrimary());

        $data = $statement->fetchAll();

        //Default fetchmode is FetchMode::ASSOCIATIVE
        self::assertArrayHasKey(0, $data);
        self::assertArrayHasKey('num', $data[0]);

        //Could be set in other fetchmodes
        self::assertArrayNotHasKey(0, $data[0]);

        self::assertEquals(1, $data[0]['num']);
    }
}

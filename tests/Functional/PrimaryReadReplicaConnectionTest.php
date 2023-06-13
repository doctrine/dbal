<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Throwable;

use function array_change_key_case;

use const CASE_LOWER;

/** @psalm-import-type Params from DriverManager */
class PrimaryReadReplicaConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Test works only on MySQL.');
        }

        try {
            $table = new Table('primary_replica_table');
            $table->addColumn('test_int', Types::INTEGER);
            $table->setPrimaryKey(['test_int']);

            $sm = $this->connection->createSchemaManager();
            $sm->createTable($table);
        } catch (Throwable) {
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
     * @return array<string,mixed>
     * @psalm-return Params
     * @phpstan-return array<string,mixed>
     */
    private function createPrimaryReadReplicaConnectionParams(bool $keepReplica = false): array
    {
        $params = $instanceParams = $this->connection->getParams();

        unset(
            $instanceParams['keepReplica'],
            $instanceParams['primary'],
            $instanceParams['replica'],
        );

        $params['primary']      = $instanceParams;
        $params['replica']      = [$instanceParams, $instanceParams];
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

            $clientCharset = $conn->fetchOne('select @@character_set_client as c');

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
        $data    = $conn->fetchAllAssociative($sql);
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
        $data    = $conn->fetchAllAssociative($sql);
        $data[0] = array_change_key_case($data[0], CASE_LOWER);

        self::assertEquals(2, $data[0]['num']);
        self::assertTrue($conn->isConnectedToPrimary());
    }

    public function testKeepReplicaBeginTransactionStaysOnPrimary(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection(true);
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

    public function testKeepReplicaInsertStaysOnPrimary(): void
    {
        $conn = $this->createPrimaryReadReplicaConnection(true);
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
}

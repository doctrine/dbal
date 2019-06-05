<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;
use Doctrine\DBAL\Sharding\ShardingException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use function array_merge;
use function assert;

/**
 * @requires extension pdo_sqlite
 */
class PoolingShardConnectionTest extends TestCase
{
    public function testConnect() : void
    {
        $conn = $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        assert($conn instanceof PoolingShardConnection);

        self::assertFalse($conn->isConnected(0));
        $conn->connect(0);
        self::assertEquals(1, $conn->fetchColumn('SELECT 1'));
        self::assertTrue($conn->isConnected(0));

        self::assertFalse($conn->isConnected(1));
        $conn->connect(1);
        self::assertEquals(1, $conn->fetchColumn('SELECT 1'));
        self::assertTrue($conn->isConnected(1));

        self::assertFalse($conn->isConnected(2));
        $conn->connect(2);
        self::assertEquals(1, $conn->fetchColumn('SELECT 1'));
        self::assertTrue($conn->isConnected(2));

        $conn->close();
        self::assertFalse($conn->isConnected(0));
        self::assertFalse($conn->isConnected(1));
        self::assertFalse($conn->isConnected(2));
    }

    public function testNoGlobalServerException() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection Parameters require "global" and "shards" configurations.');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testNoShardsServersException() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection Parameters require "global" and "shards" configurations.');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testNoShardsChoserException() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Shard Choser configuration "shardChoser".');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
        ]);
    }

    public function testShardChoserWrongInstance() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "shardChoser" configuration is not a valid instance of Doctrine\DBAL\Sharding\ShardChoser\ShardChoser');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => new stdClass(),
        ]);
    }

    public function testShardNonNumericId() : void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Shard Id has to be a non-negative number.');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 'foo', 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testShardMissingId() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "id" for one configured shard. Please specify a unique shard-id.');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testDuplicateShardId() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard "1" is duplicated in the configuration.');

        $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testSwitchShardWithOpenTransactionException() : void
    {
        $conn = $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        assert($conn instanceof PoolingShardConnection);

        $conn->beginTransaction();

        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('Cannot switch shard when transaction is active.');
        $conn->connect(1);
    }

    public function testGetActiveShardId() : void
    {
        $conn = $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        assert($conn instanceof PoolingShardConnection);

        self::assertNull($conn->getActiveShardId());

        $conn->connect(0);
        self::assertEquals(0, $conn->getActiveShardId());

        $conn->connect(1);
        self::assertEquals(1, $conn->getActiveShardId());

        $conn->close();
        self::assertNull($conn->getActiveShardId());
    }

    public function testGetParamsOverride() : void
    {
        $conn = $this->createConnection([
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        assert($conn instanceof PoolingShardConnection);

        self::assertEquals([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChoser' => new MultiTenantShardChoser(),
            'memory' => true,
            'host' => 'localhost',
        ], $conn->getParams());

        $conn->connect(1);
        self::assertEquals([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChoser' => new MultiTenantShardChoser(),
            'id' => 1,
            'memory' => true,
            'host' => 'foo',
        ], $conn->getParams());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createConnection(array $parameters) : PoolingShardConnection
    {
        $connection = DriverManager::getConnection(array_merge(
            ['wrapperClass' => PoolingShardConnection::class],
            $parameters
        ));

        self::assertInstanceOf(PoolingShardConnection::class, $connection);

        return $connection;
    }
}

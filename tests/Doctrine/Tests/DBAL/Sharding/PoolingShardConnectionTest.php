<?php

namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;
use Doctrine\DBAL\Sharding\ShardingException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @requires extension pdo_sqlite
 */
class PoolingShardConnectionTest extends TestCase
{
    public function testConnect()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

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

    public function testNoGlobalServerException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Connection Parameters require 'global' and 'shards' configurations.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testNoShardsServersException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Connection Parameters require 'global' and 'shards' configurations.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testNoShardsChoserException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Missing Shard Choser configuration 'shardChoser'");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
        ]);
    }

    public function testShardChoserWrongInstance()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("The 'shardChoser' configuration is not a valid instance of Doctrine\DBAL\Sharding\ShardChoser\ShardChoser");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChoser' => new stdClass(),
        ]);
    }

    public function testShardNonNumericId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Shard Id has to be a non-negative number.');

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 'foo', 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testShardMissingId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Missing 'id' for one configured shard. Please specify a unique shard-id.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testDuplicateShardId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Shard 1 is duplicated in the configuration.');

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);
    }

    public function testSwitchShardWithOpenTransactionException()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        $conn->beginTransaction();

        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('Cannot switch shard when transaction is active.');
        $conn->connect(1);
    }

    public function testGetActiveShardId()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        self::assertNull($conn->getActiveShardId());

        $conn->connect(0);
        self::assertEquals(0, $conn->getActiveShardId());

        $conn->connect(1);
        self::assertEquals(1, $conn->getActiveShardId());

        $conn->close();
        self::assertNull($conn->getActiveShardId());
    }

    public function testGetParamsOverride()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

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

    public function testGetHostOverride()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'host' => 'localhost',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        self::assertEquals('localhost', $conn->getHost());

        $conn->connect(1);
        self::assertEquals('foo', $conn->getHost());
    }

    public function testGetPortOverride()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'port' => 3306,
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'port' => 3307],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        self::assertEquals(3306, $conn->getPort());

        $conn->connect(1);
        self::assertEquals(3307, $conn->getPort());
    }

    public function testGetUsernameOverride()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'user' => 'foo',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'user' => 'bar'],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        self::assertEquals('foo', $conn->getUsername());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getUsername());
    }

    public function testGetPasswordOverride()
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'password' => 'foo',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'password' => 'bar'],
            ],
            'shardChoser' => MultiTenantShardChoser::class,
        ]);

        self::assertEquals('foo', $conn->getPassword());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getPassword());
    }
}

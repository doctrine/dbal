<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;

/**
 * @requires extension pdo_sqlite
 */
class PoolingShardConnectionTest extends \PHPUnit\Framework\TestCase
{
    public function testConnect()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 2, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

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

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 2, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testNoShardsServersException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Connection Parameters require 'global' and 'shards' configurations.");

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testNoShardsChoserException()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Missing Shard Choser configuration 'shardChoser'");

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 2, 'memory' => true),
            ),
        ));
    }

    public function testShardChoserWrongInstance()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("The 'shardChoser' configuration is not a valid instance of Doctrine\DBAL\Sharding\ShardChoser\ShardChoser");

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 2, 'memory' => true),
            ),
            'shardChoser' => new \stdClass,
        ));
    }

    public function testShardNonNumericId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Shard Id has to be a non-negative number.');

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 'foo', 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testShardMissingId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage("Missing 'id' for one configured shard. Please specify a unique shard-id.");

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testDuplicateShardId()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Shard 1 is duplicated in the configuration.');

        DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 1, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testSwitchShardWithOpenTransactionException()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        $conn->beginTransaction();

        $this->expectException('Doctrine\DBAL\Sharding\ShardingException');
        $this->expectExceptionMessage('Cannot switch shard when transaction is active.');
        $conn->connect(1);
    }

    public function testGetActiveShardId()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

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
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true, 'host' => 'localhost'),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'host' => 'foo'),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        self::assertEquals(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true, 'host' => 'localhost'),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'host' => 'foo'),
            ),
            'shardChoser' => new MultiTenantShardChoser(),
            'memory' => true,
            'host' => 'localhost',
        ), $conn->getParams());

        $conn->connect(1);
        self::assertEquals(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true, 'host' => 'localhost'),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'host' => 'foo'),
            ),
            'shardChoser' => new MultiTenantShardChoser(),
            'id' => 1,
            'memory' => true,
            'host' => 'foo',
        ), $conn->getParams());
    }

    public function testGetHostOverride()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'host' => 'localhost',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'host' => 'foo'),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        self::assertEquals('localhost', $conn->getHost());

        $conn->connect(1);
        self::assertEquals('foo', $conn->getHost());
    }

    public function testGetPortOverride()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'port' => 3306,
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'port' => 3307),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        self::assertEquals(3306, $conn->getPort());

        $conn->connect(1);
        self::assertEquals(3307, $conn->getPort());
    }

    public function testGetUsernameOverride()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'user' => 'foo',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'user' => 'bar'),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        self::assertEquals('foo', $conn->getUsername());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getUsername());
    }

    public function testGetPasswordOverride()
    {
        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'password' => 'foo',
            'global' => array('memory' => true),
            'shards' => array(
                array('id' => 1, 'memory' => true, 'password' => 'bar'),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));

        self::assertEquals('foo', $conn->getPassword());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getPassword());
    }
}

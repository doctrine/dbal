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

class PoolingShardConnectionTest extends \PHPUnit_Framework_TestCase
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

        $this->assertFalse($conn->isConnected(0));
        $conn->connect(0);
        $this->assertEquals(1, $conn->fetchColumn('SELECT 1'));
        $this->assertTrue($conn->isConnected(0));

        $this->assertFalse($conn->isConnected(1));
        $conn->connect(1);
        $this->assertEquals(1, $conn->fetchColumn('SELECT 1'));
        $this->assertTrue($conn->isConnected(1));

        $this->assertFalse($conn->isConnected(2));
        $conn->connect(2);
        $this->assertEquals(1, $conn->fetchColumn('SELECT 1'));
        $this->assertTrue($conn->isConnected(2));

        $conn->close();
        $this->assertFalse($conn->isConnected(0));
        $this->assertFalse($conn->isConnected(1));
        $this->assertFalse($conn->isConnected(2));
    }

    public function testNoGlobalServerException()
    {
        $this->setExpectedException('InvalidArgumentException', "Connection Parameters require 'global' and 'shards' configurations.");

        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'shards' => array(
                array('id' => 1, 'memory' => true),
                array('id' => 2, 'memory' => true),
            ),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testNoShardsServersExecption()
    {
        $this->setExpectedException('InvalidArgumentException', "Connection Parameters require 'global' and 'shards' configurations.");

        $conn = DriverManager::getConnection(array(
            'wrapperClass' => 'Doctrine\DBAL\Sharding\PoolingShardConnection',
            'driver' => 'pdo_sqlite',
            'global' => array('memory' => true),
            'shardChoser' => 'Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser',
        ));
    }

    public function testNoShardsChoserExecption()
    {
        $this->setExpectedException('InvalidArgumentException', "Missing Shard Choser configuration 'shardChoser'");

        $conn = DriverManager::getConnection(array(
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
        $this->setExpectedException('InvalidArgumentException', "The 'shardChoser' configuration is not a valid instance of Doctrine\DBAL\Sharding\ShardChoser\ShardChoser");

        $conn = DriverManager::getConnection(array(
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
        $this->setExpectedException('InvalidArgumentException', "Shard Id has to be a non-negative number.");

        $conn = DriverManager::getConnection(array(
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
        $this->setExpectedException('InvalidArgumentException', "Missing 'id' for one configured shard. Please specify a unique shard-id.");

        $conn = DriverManager::getConnection(array(
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
        $this->setExpectedException('InvalidArgumentException', "Shard 1 is duplicated in the configuration.");

        $conn = DriverManager::getConnection(array(
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

        $this->setExpectedException('Doctrine\DBAL\Sharding\ShardingException', 'Cannot switch shard when transaction is active.');
        $conn->connect(1);
    }
}


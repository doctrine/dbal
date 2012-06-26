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

use Doctrine\DBAL\Sharding\PoolingShardManager;

class PoolingShardManagerTest extends \PHPUnit_Framework_TestCase
{
    private function createConnectionMock()
    {
        return $this->getMock('Doctrine\DBAL\Sharding\PoolingShardConnection', array('connect', 'getParams', 'fetchAll'), array(), '', false);
    }

    private function createPassthroughShardChoser()
    {
        $mock = $this->getMock('Doctrine\DBAL\Sharding\ShardChoser\ShardChoser');
        $mock->expects($this->any())
             ->method('pickShard')
             ->will($this->returnCallback(function($value) { return $value; }));
        return $mock;
    }

    public function testSelectGlobal()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->once())->method('connect')->with($this->equalTo(0));

        $shardManager = new PoolingShardManager($conn, $this->createPassthroughShardChoser());
        $shardManager->selectGlobal();

        $this->assertNull($shardManager->getCurrentDistributionValue());
    }

    public function testSelectShard()
    {
        $shardId = 10;
        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(array('shardChoser' => $this->createPassthroughShardChoser())));
        $conn->expects($this->at(1))->method('connect')->with($this->equalTo($shardId));

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectShard($shardId);

        $this->assertEquals($shardId, $shardManager->getCurrentDistributionValue());
    }

    public function testGetShards()
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->any())->method('getParams')->will(
            $this->returnValue(
                array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
            )
        );

        $shardManager = new PoolingShardManager($conn, $this->createPassthroughShardChoser());
        $shards = $shardManager->getShards();

        $this->assertEquals(array(array('id' => 1), array('id' => 2)), $shards);
    }

    public function testQueryAll()
    {
        $sql = "SELECT * FROM table";
        $params = array(1);
        $types = array(1);

        $conn = $this->createConnectionMock();
        $conn->expects($this->at(0))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
        ));
        $conn->expects($this->at(1))->method('getParams')->will($this->returnValue(
            array('shards' => array( array('id' => 1), array('id' => 2) ), 'shardChoser' => $this->createPassthroughShardChoser())
        ));
        $conn->expects($this->at(2))->method('connect')->with($this->equalTo(1));
        $conn->expects($this->at(3))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue(array( array('id' => 1) ) ));
        $conn->expects($this->at(4))->method('connect')->with($this->equalTo(2));
        $conn->expects($this->at(5))
             ->method('fetchAll')
             ->with($this->equalTo($sql), $this->equalTo($params), $this->equalTo($types))
             ->will($this->returnValue(array( array('id' => 2) ) ));

        $shardManager = new PoolingShardManager($conn, $this->createPassthroughShardChoser());
        $result = $shardManager->queryAll($sql, $params, $types);

        $this->assertEquals(array(array('id' => 1), array('id' => 2)), $result);
    }
}


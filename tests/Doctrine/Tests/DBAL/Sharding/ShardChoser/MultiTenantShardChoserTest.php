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

namespace Doctrine\Tests\DBAL\Sharding\ShardChoser;

use Doctrine\DBAL\Sharding\ShardChoser\MultiTenantShardChoser;

class MultiTenantShardChoserTest extends \PHPUnit_Framework_TestCase
{
    public function testPickShard()
    {
        $choser = new MultiTenantShardChoser();
        $conn = $this->createConnectionMock();

        $this->assertEquals(1, $choser->pickShard(1, $conn));
        $this->assertEquals(2, $choser->pickShard(2, $conn));
    }

    private function createConnectionMock()
    {
        return $this->getMock('Doctrine\DBAL\Sharding\PoolingShardConnection', array('connect', 'getParams', 'fetchAll'), array(), '', false);
    }
}


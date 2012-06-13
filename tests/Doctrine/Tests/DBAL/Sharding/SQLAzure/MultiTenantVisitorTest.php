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

namespace Doctrine\Tests\DBAL\Sharding\SQLAzure;

use Doctrine\DBAL\Platforms\SQLAzurePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Sharding\SQLAzure\Schema\MultiTenantVisitor;

class MultiTenantVisitorTest extends \PHPUnit_Framework_TestCase
{
    public function testMultiTenantPrimaryKey()
    {
        $platform = new SQLAzurePlatform();
        $visitor = new MultiTenantVisitor();

        $schema = new Schema();
        $foo = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->setPrimaryKey(array('id'));
        $schema->visit($visitor);

        $this->assertEquals(array('id', 'tenant_id'), $foo->getPrimaryKey()->getColumns());
        $this->assertTrue($foo->hasColumn('tenant_id'));
    }

    public function testMultiTenantNonPrimaryKey()
    {
        $platform = new SQLAzurePlatform();
        $visitor = new MultiTenantVisitor();

        $schema = new Schema();
        $foo = $schema->createTable('foo');
        $foo->addColumn('id', 'string');
        $foo->addColumn('created', 'datetime');
        $foo->setPrimaryKey(array('id'));
        $foo->addIndex(array('created'), 'idx');

        $foo->getPrimaryKey()->addFlag('nonclustered');
        $foo->getIndex('idx')->addFlag('clustered');

        $schema->visit($visitor);

        $this->assertEquals(array('id'), $foo->getPrimaryKey()->getColumns());
        $this->assertTrue($foo->hasColumn('tenant_id'));
        $this->assertEquals(array('created', 'tenant_id'), $foo->getIndex('idx')->getColumns());
    }
}


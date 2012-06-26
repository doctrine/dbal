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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Tests\DBAL\Schema\Synchronizer;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;

class SingleDatabaseSynchronizerTest extends \PHPUnit_Framework_TestCase
{
    private $conn;
    private $synchronizer;

    public function setUp()
    {
        $this->conn = DriverManager::getConnection(array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ));
        $this->synchronizer = new SingleDatabaseSynchronizer($this->conn);
    }

    public function testGetCreateSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $sql = $this->synchronizer->getCreateSchema($schema);
        $this->assertEquals(array('CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'), $sql);
    }

    public function testGetUpdateSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $sql = $this->synchronizer->getUpdateSchema($schema);
        $this->assertEquals(array('CREATE TABLE test (id INTEGER NOT NULL, PRIMARY KEY(id))'), $sql);
    }

    public function testGetDropSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropSchema($schema);
        $this->assertEquals(array('DROP TABLE test'), $sql);
    }

    public function testGetDropAllSchema()
    {
        $schema = new Schema();
        $table = $schema->createTable('test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->synchronizer->createSchema($schema);

        $sql = $this->synchronizer->getDropAllSchema();
        $this->assertEquals(array('DROP TABLE test'), $sql);
    }
}


<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

/**
 * @group DBAL-6
 */
class BlobTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("blob_table");
            $table->addColumn('id', 'integer');
            $table->addColumn('clobfield', 'text');
            $table->addColumn('blobfield', 'blob');
            $table->setPrimaryKey(array('id'));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch(\Exception $e) {

        }
        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('blob_table'));
    }

    public function testInsert()
    {
        $ret = $this->_conn->insert('blob_table',
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test'),
            array(\PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB)
        );
        $this->assertEquals(1, $ret);
    }

    public function testSelect()
    {
        $ret = $this->_conn->insert('blob_table',
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test'),
            array(\PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB)
        );

        $this->assertBlobContains('test');
    }

    public function testUpdate()
    {
        $ret = $this->_conn->insert('blob_table',
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test'),
            array(\PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB)
        );

        $this->_conn->update('blob_table',
            array('blobfield' => 'test2'),
            array('id' => 1),
            array(\PDO::PARAM_LOB, \PDO::PARAM_INT)
        );

        $this->assertBlobContains('test2');
    }

    private function assertBlobContains($text)
    {
        $rows = $this->_conn->fetchAll('SELECT * FROM blob_table');

        $this->assertEquals(1, count($rows));
        $row = array_change_key_case($rows[0], CASE_LOWER);

        $blobValue = Type::getType('blob')->convertToPHPValue($row['blobfield'], $this->_conn->getDatabasePlatform());

        $this->assertInternalType('resource', $blobValue);
        $this->assertEquals($text, stream_get_contents($blobValue));
    }
}
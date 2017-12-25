<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

/**
 * @group DBAL-6
 */
class BlobTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\PDOSqlsrv\Driver) {
            $this->markTestSkipped('This test does not work on pdo_sqlsrv driver due to a bug. See: http://social.msdn.microsoft.com/Forums/sqlserver/en-US/5a755bdd-41e9-45cb-9166-c9da4475bb94/how-to-set-null-for-varbinarymax-using-bindvalue-using-pdosqlsrv?forum=sqldriverforphp');
        }

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("blob_table");
            $table->addColumn('id', 'integer');
            $table->addColumn('clobfield', 'text');
            $table->addColumn('blobfield', 'blob');
            $table->addColumn('binaryfield', 'binary', array('length' => 50));
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
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test', 'binaryfield' => 'test'),
            array(ParameterType::INTEGER, ParameterType::STRING, ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT)
        );
        self::assertEquals(1, $ret);
    }

    public function testSelect()
    {
        $this->_conn->insert('blob_table',
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test', 'binaryfield' => 'test'),
            array(ParameterType::INTEGER, ParameterType::STRING, ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT)
        );

        $this->assertBlobContains('test');
    }

    public function testUpdate()
    {
        $this->_conn->insert('blob_table',
            array('id' => 1, 'clobfield' => 'test', 'blobfield' => 'test', 'binaryfield' => 'test'),
            array(ParameterType::INTEGER, ParameterType::STRING, ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT)
        );

        $this->_conn->update('blob_table',
            array('blobfield' => 'test2', 'binaryfield' => 'test2'),
            array('id' => 1),
            array(ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT, ParameterType::INTEGER)
        );

        $this->assertBlobContains('test2');
        $this->assertBinaryContains('test2');
    }

    private function assertBinaryContains($text)
    {
        $rows = $this->_conn->fetchAll('SELECT * FROM blob_table');

        self::assertCount(1, $rows);
        $row = array_change_key_case($rows[0], CASE_LOWER);

        $blobValue = Type::getType('binary')->convertToPHPValue($row['binaryfield'], $this->_conn->getDatabasePlatform());

        self::assertInternalType('resource', $blobValue);
        self::assertEquals($text, stream_get_contents($blobValue));
    }

    private function assertBlobContains($text)
    {
        $rows = $this->_conn->fetchAll('SELECT * FROM blob_table');

        self::assertCount(1, $rows);
        $row = array_change_key_case($rows[0], CASE_LOWER);

        $blobValue = Type::getType('blob')->convertToPHPValue($row['blobfield'], $this->_conn->getDatabasePlatform());

        self::assertInternalType('resource', $blobValue);
        self::assertEquals($text, stream_get_contents($blobValue));
    }
}

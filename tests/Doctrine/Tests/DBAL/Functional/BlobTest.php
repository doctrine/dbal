<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as PDOSQLSrvDriver;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use const CASE_LOWER;
use function array_change_key_case;
use function stream_get_contents;

/**
 * @group DBAL-6
 */
class BlobTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getDriver() instanceof PDOSQLSrvDriver) {
            $this->markTestSkipped('This test does not work on pdo_sqlsrv driver due to a bug. See: http://social.msdn.microsoft.com/Forums/sqlserver/en-US/5a755bdd-41e9-45cb-9166-c9da4475bb94/how-to-set-null-for-varbinarymax-using-bindvalue-using-pdosqlsrv?forum=sqldriverforphp');
        }

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $table = new Table('blob_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('clobfield', 'text');
        $table->addColumn('blobfield', 'blob');
        $table->setPrimaryKey(['id']);

        $sm = $this->_conn->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsert()
    {
        $ret = $this->_conn->insert('blob_table', [
            'id'          => 1,
            'clobfield'   => 'test',
            'blobfield'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertEquals(1, $ret);
    }

    public function testSelect()
    {
        $this->_conn->insert('blob_table', [
            'id'          => 1,
            'clobfield'   => 'test',
            'blobfield'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test');
    }

    public function testUpdate()
    {
        $this->_conn->insert('blob_table', [
            'id' => 1,
            'clobfield' => 'test',
            'blobfield' => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->_conn->update('blob_table', [
            'blobfield' => 'test2',
        ], ['id' => 1], [
            ParameterType::LARGE_OBJECT,
            ParameterType::INTEGER,
        ]);

        $this->assertBlobContains('test2');
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

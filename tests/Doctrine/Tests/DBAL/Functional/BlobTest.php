<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as PDOSQLSrvDriver;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use function fopen;
use function in_array;
use function str_repeat;
use function stream_get_contents;

/**
 * @group DBAL-6
 */
class BlobTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof PDOSQLSrvDriver) {
            $this->markTestSkipped('This test does not work on pdo_sqlsrv driver due to a bug. See: http://social.msdn.microsoft.com/Forums/sqlserver/en-US/5a755bdd-41e9-45cb-9166-c9da4475bb94/how-to-set-null-for-varbinarymax-using-bindvalue-using-pdosqlsrv?forum=sqldriverforphp');
        }

        /** @var AbstractSchemaManager $sm */
        $table = new Table('blob_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('clobfield', 'text');
        $table->addColumn('blobfield', 'blob');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsert()
    {
        $ret = $this->connection->insert('blob_table', [
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

    public function testInsertProcessesStream()
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            // https://github.com/doctrine/dbal/issues/3288 for DB2
            // https://github.com/doctrine/dbal/issues/3290 for Oracle
            $this->markTestIncomplete('Platform does not support stream resources as parameters');
        }

        $longBlob = str_repeat('x', 4 * 8192); // send 4 chunks
        $this->connection->insert('blob_table', [
            'id'        => 1,
            'clobfield' => 'ignored',
            'blobfield' => fopen('data://text/plain,' . $longBlob, 'r'),
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains($longBlob);
    }

    public function testSelect()
    {
        $this->connection->insert('blob_table', [
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
        $this->connection->insert('blob_table', [
            'id' => 1,
            'clobfield' => 'test',
            'blobfield' => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', ['blobfield' => 'test2'], ['id' => 1], [
            ParameterType::LARGE_OBJECT,
            ParameterType::INTEGER,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testUpdateProcessesStream()
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            // https://github.com/doctrine/dbal/issues/3288 for DB2
            // https://github.com/doctrine/dbal/issues/3290 for Oracle
            $this->markTestIncomplete('Platform does not support stream resources as parameters');
        }

        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobfield'   => 'ignored',
            'blobfield'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', [
            'id'          => 1,
            'blobfield'   => fopen('data://text/plain,test2', 'r'),
        ], ['id' => 1], [
            ParameterType::INTEGER,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testBindParamProcessesStream()
    {
        if (in_array($this->connection->getDatabasePlatform()->getName(), ['oracle', 'db2'], true)) {
            // https://github.com/doctrine/dbal/issues/3288 for DB2
            // https://github.com/doctrine/dbal/issues/3290 for Oracle
            $this->markTestIncomplete('Platform does not support stream resources as parameters');
        }

        $stmt = $this->connection->prepare("INSERT INTO blob_table(id, clobfield, blobfield) VALUES (1, 'ignored', ?)");

        $stream = null;
        $stmt->bindParam(1, $stream, ParameterType::LARGE_OBJECT);

        // Bind param does late binding (bind by reference), so create the stream only now:
        $stream = fopen('data://text/plain,test', 'r');

        $stmt->execute();

        $this->assertBlobContains('test');
    }

    private function assertBlobContains($text)
    {
        $rows = $this->connection->query('SELECT blobfield FROM blob_table')->fetchAll(FetchMode::COLUMN);

        self::assertCount(1, $rows);

        $blobValue = Type::getType('blob')->convertToPHPValue($rows[0], $this->connection->getDatabasePlatform());

        self::assertInternalType('resource', $blobValue);
        self::assertEquals($text, stream_get_contents($blobValue));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function fopen;
use function str_repeat;
use function stream_get_contents;

class BlobTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped("DBAL doesn't support storing LOBs represented as streams using PDO_OCI");
        }

        $table = new Table('blob_table');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('clobcolumn', Types::TEXT, ['notnull' => false]);
        $table->addColumn('blobcolumn', Types::BLOB, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    public function testInsert(): void
    {
        $ret = $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertEquals(1, $ret);
    }

    public function testInsertNull(): void
    {
        $ret = $this->connection->insert('blob_table', [
            'id'         => 1,
            'clobcolumn' => null,
            'blobcolumn' => null,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        self::assertEquals(1, $ret);

        $row = $this->fetchRow();
        self::assertCount(2, $row);

        self::assertNull($row[0]);
        self::assertNull($row[1]);
    }

    public function testInsertProcessesStream(): void
    {
        // https://github.com/doctrine/dbal/issues/3290
        if (TestUtil::isDriverOneOf('oci8')) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $longBlob = str_repeat('x', 4 * 8192); // send 4 chunks
        $this->connection->insert('blob_table', [
            'id'        => 1,
            'clobcolumn' => 'ignored',
            'blobcolumn' => fopen('data://text/plain,' . $longBlob, 'r'),
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains($longBlob);
    }

    public function testSelect(): void
    {
        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'test',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test');
    }

    public function testUpdate(): void
    {
        $this->connection->insert('blob_table', [
            'id' => 1,
            'clobcolumn' => 'test',
            'blobcolumn' => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', ['blobcolumn' => 'test2'], ['id' => 1], [
            ParameterType::LARGE_OBJECT,
            ParameterType::INTEGER,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testUpdateProcessesStream(): void
    {
        // https://github.com/doctrine/dbal/issues/3290
        if (TestUtil::isDriverOneOf('oci8')) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $this->connection->insert('blob_table', [
            'id'          => 1,
            'clobcolumn'   => 'ignored',
            'blobcolumn'   => 'test',
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->connection->update('blob_table', [
            'id'          => 1,
            'blobcolumn'   => fopen('data://text/plain,test2', 'r'),
        ], ['id' => 1], [
            ParameterType::INTEGER,
            ParameterType::LARGE_OBJECT,
        ]);

        $this->assertBlobContains('test2');
    }

    public function testBindParamProcessesStream(): void
    {
        if (TestUtil::isDriverOneOf('oci8')) {
            self::markTestIncomplete('The oci8 driver does not support stream resources as parameters');
        }

        $stmt = $this->connection->prepare(
            "INSERT INTO blob_table(id, clobcolumn, blobcolumn) VALUES (1, 'ignored', ?)",
        );

        $stream = fopen('data://text/plain,test', 'r');
        $stmt->bindValue(1, $stream, ParameterType::LARGE_OBJECT);

        $stmt->executeStatement();

        $this->assertBlobContains('test');
    }

    public function testBlobBindingDoesNotOverwritePrevious(): void
    {
        $table = new Table('blob_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('blobcolumn1', 'blob', ['notnull' => false]);
        $table->addColumn('blobcolumn2', 'blob', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $params = ['test1', 'test2'];
        $this->connection->executeStatement(
            'INSERT INTO blob_table(id, blobcolumn1, blobcolumn2) VALUES (1, ?, ?)',
            $params,
            [ParameterType::LARGE_OBJECT, ParameterType::LARGE_OBJECT],
        );

        $blobs = $this->connection->fetchNumeric('SELECT blobcolumn1, blobcolumn2 FROM blob_table');
        self::assertIsArray($blobs);

        $actual = [];
        foreach ($blobs as $blob) {
            $blob     = Type::getType('blob')->convertToPHPValue($blob, $this->connection->getDatabasePlatform());
            $actual[] = stream_get_contents($blob);
        }

        self::assertEquals(['test1', 'test2'], $actual);
    }

    private function assertBlobContains(string $text): void
    {
        [, $blobValue] = $this->fetchRow();

        $blobValue = Type::getType(Types::BLOB)->convertToPHPValue(
            $blobValue,
            $this->connection->getDatabasePlatform(),
        );

        self::assertIsResource($blobValue);
        self::assertEquals($text, stream_get_contents($blobValue));
    }

    /** @return list<mixed> */
    private function fetchRow(): array
    {
        $rows = $this->connection->fetchAllNumeric('SELECT clobcolumn, blobcolumn FROM blob_table');

        self::assertCount(1, $rows);

        return $rows[0];
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Closure;
use DateTime;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Throwable;

class WriteTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('write_table');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('test_int', Types::INTEGER);
        $table->addColumn('test_string', Types::STRING, [
            'length' => 32,
            'notnull' => false,
        ]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->executeStatement('DELETE FROM write_table');
    }

    public function testExecuteUpdate(): void
    {
        $sql      = 'INSERT INTO write_table (test_int) VALUES (1)';
        $affected = $this->connection->executeStatement($sql);

        self::assertEquals(1, $affected, 'executeStatement() should return the number of affected rows!');
    }

    public function testExecuteStatementWithTypes(): void
    {
        $sql      = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $affected = $this->connection->executeStatement(
            $sql,
            [1, 'foo'],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        self::assertEquals(1, $affected, 'executeStatement() should return the number of affected rows!');
    }

    public function testPrepareRowCountReturnsAffectedRows(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');

        self::assertEquals(1, $stmt->executeStatement());
    }

    public function testPrepareWithPrimitiveTypes(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);

        self::assertEquals(1, $stmt->executeStatement());
    }

    public function testPrepareWithDoctrineMappingTypes(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);

        self::assertEquals(1, $stmt->executeStatement());
    }

    public function testPrepareWithDoctrineMappingTypeNames(): void
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);

        self::assertEquals(1, $stmt->executeStatement());
    }

    public function insertRows(): void
    {
        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 1, 'test_string' => 'foo']));
        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
    }

    public function testInsert(): void
    {
        $this->insertRows();
    }

    public function testDelete(): void
    {
        $this->insertRows();

        self::assertEquals(1, $this->connection->delete('write_table', ['test_int' => 2]));
        self::assertCount(1, $this->connection->fetchAllAssociative('SELECT * FROM write_table'));

        self::assertEquals(1, $this->connection->delete('write_table', ['test_int' => 1]));
        self::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM write_table'));
    }

    public function testDeleteAll(): void
    {
        $this->insertRows();

        self::assertEquals(2, $this->connection->delete('write_table'));
        self::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM write_table'));
    }

    public function testUpdate(): void
    {
        $this->insertRows();

        self::assertEquals(1, $this->connection->update(
            'write_table',
            ['test_string' => 'bar'],
            ['test_string' => 'foo'],
        ));

        self::assertEquals(2, $this->connection->update(
            'write_table',
            ['test_string' => 'baz'],
            ['test_string' => 'bar'],
        ));

        self::assertEquals(0, $this->connection->update(
            'write_table',
            ['test_string' => 'baz'],
            ['test_string' => 'bar'],
        ));
    }

    public function testUpdateAll(): void
    {
        $this->insertRows();

        self::assertEquals(2, $this->connection->update(
            'write_table',
            ['test_string' => 'baz'],
        ));
    }

    public function testLastInsertId(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('This test targets platforms that support identity columns.');
        }

        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
        $num = $this->connection->lastInsertId();

        self::assertGreaterThan(0, $num, 'LastInsertId() should be non-negative number.');
    }

    public function testLastInsertIdNotSupported(): void
    {
        if ($this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('This test targets platforms that don\'t support identity columns.');
        }

        $this->expectDriverException(IdentityColumnsNotSupported::class, function (): void {
            $this->connection->lastInsertId();
        });
    }

    public function testLastInsertIdNewConnection(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            self::markTestSkipped('This test targets platforms that support identity columns.');
        }

        $connection = TestUtil::getConnection();

        $this->expectDriverException(NoIdentityValue::class, static function () use ($connection): void {
            $connection->lastInsertId();
        });
    }

    public function testInsertWithKeyValueTypes(): void
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals(
            $testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()),
            $data,
        );
    }

    public function testUpdateWithKeyValueTypes(): void
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $testString = new DateTime('2013-04-15 10:10:10');

        $this->connection->update(
            'write_table',
            ['test_string' => $testString],
            ['test_int' => '30'],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals(
            $testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()),
            $data,
        );
    }

    public function testDeleteWithKeyValueTypes(): void
    {
        $val = new DateTime('2013-04-14 10:10:10');
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $val],
            ['test_string' => 'datetime', 'test_int' => 'integer'],
        );

        $this->connection->delete('write_table', [
            'test_int' => 30,
            'test_string' => $val,
        ], [
            'test_string' => 'datetime',
            'test_int' => 'integer',
        ]);

        $data = $this->connection->fetchOne('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertFalse($data);
    }

    public function testEmptyIdentityInsert(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! $platform->supportsIdentityColumns()) {
            self::markTestSkipped(
                'Test only works on platforms with native support for identity columns.',
            );
        }

        $table = new Table('test_empty_identity');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        try {
            $this->connection->createSchemaManager()->dropTable($table->getQuotedName($platform));
        } catch (Throwable) {
        }

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $sql = $platform->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->connection->executeStatement($sql);

        $firstId = $this->connection->lastInsertId();

        $this->connection->executeStatement($sql);

        $secondId = $this->connection->lastInsertId();

        self::assertGreaterThan($firstId, $secondId);
    }

    public function testUpdateWhereIsNull(): void
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->update('write_table', ['test_int' => 10], ['test_string' => null], [
            'test_string' => 'string',
            'test_int' => 'integer',
        ]);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    public function testDeleteWhereIsNull(): void
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer'],
        );

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->delete('write_table', ['test_string' => null], ['test_string' => 'string']);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    /** @param class-string<Driver\Exception> $expectedClass */
    private function expectDriverException(string $expectedClass, Closure $test): void
    {
        try {
            $test();
        } catch (DriverException $e) {
            self::assertInstanceOf($expectedClass, $e->getPrevious());
        }
    }
}

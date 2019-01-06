<?php

namespace Doctrine\Tests\DBAL\Functional;

use DateTime;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;
use function array_filter;
use function strtolower;

class WriteTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        try {
            $table = new Table('write_table');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string', ['notnull' => false]);
            $table->setPrimaryKey(['id']);

            $this->connection->getSchemaManager()->createTable($table);
        } catch (Throwable $e) {
        }
        $this->connection->executeUpdate('DELETE FROM write_table');
    }

    /**
     * @group DBAL-80
     */
    public function testExecuteUpdateFirstTypeIsNull()
    {
        $sql = 'INSERT INTO write_table (test_string, test_int) VALUES (?, ?)';
        $this->connection->executeUpdate($sql, ['text', 1111], [null, ParameterType::INTEGER]);

        $sql = 'SELECT * FROM write_table WHERE test_string = ? AND test_int = ?';
        self::assertTrue((bool) $this->connection->fetchColumn($sql, ['text', 1111]));
    }

    public function testExecuteUpdate()
    {
        $sql      = 'INSERT INTO write_table (test_int) VALUES ( ' . $this->connection->quote(1) . ')';
        $affected = $this->connection->executeUpdate($sql);

        self::assertEquals(1, $affected, 'executeUpdate() should return the number of affected rows!');
    }

    public function testExecuteUpdateWithTypes()
    {
        $sql      = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $affected = $this->connection->executeUpdate(
            $sql,
            [1, 'foo'],
            [ParameterType::INTEGER, ParameterType::STRING]
        );

        self::assertEquals(1, $affected, 'executeUpdate() should return the number of affected rows!');
    }

    public function testPrepareRowCountReturnsAffectedRows()
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, 'foo');
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithPdoTypes()
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypes()
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType('integer'));
        $stmt->bindValue(2, 'foo', Type::getType('string'));
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypeNames()
    {
        $sql  = 'INSERT INTO write_table (test_int, test_string) VALUES (?, ?)';
        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, 'foo', 'string');
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function insertRows()
    {
        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 1, 'test_string' => 'foo']));
        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
    }

    public function testInsert()
    {
        $this->insertRows();
    }

    public function testDelete()
    {
        $this->insertRows();

        self::assertEquals(1, $this->connection->delete('write_table', ['test_int' => 2]));
        self::assertCount(1, $this->connection->fetchAll('SELECT * FROM write_table'));

        self::assertEquals(1, $this->connection->delete('write_table', ['test_int' => 1]));
        self::assertCount(0, $this->connection->fetchAll('SELECT * FROM write_table'));
    }

    public function testUpdate()
    {
        $this->insertRows();

        self::assertEquals(1, $this->connection->update('write_table', ['test_string' => 'bar'], ['test_string' => 'foo']));
        self::assertEquals(2, $this->connection->update('write_table', ['test_string' => 'baz'], ['test_string' => 'bar']));
        self::assertEquals(0, $this->connection->update('write_table', ['test_string' => 'baz'], ['test_string' => 'bar']));
    }

    public function testLastInsertId()
    {
        if (! $this->connection->getDatabasePlatform()->prefersIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        self::assertEquals(1, $this->connection->insert('write_table', ['test_int' => 2, 'test_string' => 'bar']));
        $num = $this->lastInsertId();

        self::assertNotNull($num, 'LastInsertId() should not be null.');
        self::assertGreaterThan(0, $num, 'LastInsertId() should be non-negative number.');
    }

    public function testLastInsertIdSequence()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped('Test only works on platforms with sequences.');
        }

        $sequence = new Sequence('write_table_id_seq');
        try {
            $this->connection->getSchemaManager()->createSequence($sequence);
        } catch (Throwable $e) {
        }

        $sequences = $this->connection->getSchemaManager()->listSequences();
        self::assertCount(1, array_filter($sequences, static function ($sequence) {
            return strtolower($sequence->getName()) === 'write_table_id_seq';
        }));

        $stmt            = $this->connection->query($this->connection->getDatabasePlatform()->getSequenceNextValSQL('write_table_id_seq'));
        $nextSequenceVal = $stmt->fetchColumn();

        $lastInsertId = $this->lastInsertId('write_table_id_seq');

        self::assertGreaterThan(0, $lastInsertId);
        self::assertEquals($nextSequenceVal, $lastInsertId);
    }

    public function testLastInsertIdNoSequenceGiven()
    {
        if (! $this->connection->getDatabasePlatform()->supportsSequences() || $this->connection->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped("Test only works consistently on platforms that support sequences and don't support identity columns.");
        }

        self::assertFalse($this->lastInsertId());
    }

    /**
     * @group DBAL-445
     */
    public function testInsertWithKeyValueTypes()
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer']
        );

        $data = $this->connection->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testUpdateWithKeyValueTypes()
    {
        $testString = new DateTime('2013-04-14 10:10:10');

        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $testString],
            ['test_string' => 'datetime', 'test_int' => 'integer']
        );

        $testString = new DateTime('2013-04-15 10:10:10');

        $this->connection->update(
            'write_table',
            ['test_string' => $testString],
            ['test_int' => '30'],
            ['test_string' => 'datetime', 'test_int' => 'integer']
        );

        $data = $this->connection->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testDeleteWithKeyValueTypes()
    {
        $val = new DateTime('2013-04-14 10:10:10');
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => $val],
            ['test_string' => 'datetime', 'test_int' => 'integer']
        );

        $this->connection->delete('write_table', ['test_int' => 30, 'test_string' => $val], ['test_string' => 'datetime', 'test_int' => 'integer']);

        $data = $this->connection->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertFalse($data);
    }

    public function testEmptyIdentityInsert()
    {
        $platform = $this->connection->getDatabasePlatform();

        if (! ($platform->supportsIdentityColumns() || $platform->usesSequenceEmulatedIdentityColumns())) {
            $this->markTestSkipped(
                'Test only works on platforms with identity columns or sequence emulated identity columns.'
            );
        }

        $table = new Table('test_empty_identity');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        try {
            $this->connection->getSchemaManager()->dropTable($table->getQuotedName($platform));
        } catch (Throwable $e) {
        }

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->connection->exec($sql);
        }

        $seqName = $platform->usesSequenceEmulatedIdentityColumns()
            ? $platform->getIdentitySequenceName('test_empty_identity', 'id')
            : null;

        $sql = $platform->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->connection->exec($sql);

        $firstId = $this->lastInsertId($seqName);

        $this->connection->exec($sql);

        $secondId = $this->lastInsertId($seqName);

        self::assertGreaterThan($firstId, $secondId);
    }

    /**
     * @group DBAL-2688
     */
    public function testUpdateWhereIsNull()
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->connection->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->update('write_table', ['test_int' => 10], ['test_string' => null], ['test_string' => 'string', 'test_int' => 'integer']);

        $data = $this->connection->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    public function testDeleteWhereIsNull()
    {
        $this->connection->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->connection->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->connection->delete('write_table', ['test_string' => null], ['test_string' => 'string']);

        $data = $this->connection->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    /**
     * Returns the ID of the last inserted row or skips the test if the currently used driver
     * doesn't support this feature
     *
     * @return string
     *
     * @throws DriverException
     */
    private function lastInsertId(?string $name = null)
    {
        try {
            return $this->connection->lastInsertId($name);
        } catch (DriverException $e) {
            if ($e->getCode() === 'IM001') {
                $this->markTestSkipped($e->getMessage());
            }

            throw $e;
        }
    }
}

<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\Tests\TestUtil;
use function array_filter;
use function strtolower;

class WriteTest extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->createTable('write_table');
    }

    private function createTable($tableName)
    {
        $table = new Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->_conn->getSchemaManager()->createTable($table);
    }

    protected function tearDown()
    {
        $this->_conn->getSchemaManager()->dropTable('write_table');

        parent::tearDown();
    }

    /**
     * @group DBAL-80
     */
    public function testExecuteUpdateFirstTypeIsNull()
    {
        $sql = "INSERT INTO write_table (test_string, test_int) VALUES (?, ?)";
        $this->_conn->executeUpdate($sql, ['text', 1111], [null, ParameterType::INTEGER]);

        $sql = "SELECT * FROM write_table WHERE test_string = ? AND test_int = ?";
        self::assertTrue((bool) $this->_conn->fetchColumn($sql, ['text', 1111]));
    }

    public function testExecuteUpdate()
    {
        $sql = "INSERT INTO write_table (test_int) VALUES ( " . $this->_conn->quote(1) . ")";
        $affected = $this->_conn->executeUpdate($sql);

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testExecuteUpdateWithTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $affected = $this->_conn->executeUpdate(
            $sql,
            [1, 'foo'],
            [ParameterType::INTEGER, ParameterType::STRING]
        );

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testPrepareRowCountReturnsAffectedRows()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, "foo");
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithPdoTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType('integer'));
        $stmt->bindValue(2, "foo", Type::getType('string'));
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypeNames()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, "foo", 'string');
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function insertRows()
    {
        self::assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 1, 'test_string' => 'foo')));
        self::assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
    }

    public function testInsert()
    {
        $this->insertRows();
    }

    public function testDelete()
    {
        $this->insertRows();

        self::assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 2)));
        self::assertCount(1, $this->_conn->fetchAll('SELECT * FROM write_table'));

        self::assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 1)));
        self::assertCount(0, $this->_conn->fetchAll('SELECT * FROM write_table'));
    }

    public function testUpdate()
    {
        $this->insertRows();

        self::assertEquals(1, $this->_conn->update('write_table', array('test_string' => 'bar'), array('test_string' => 'foo')));
        self::assertEquals(2, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
        self::assertEquals(0, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
    }

    public function testLastInsertIdNoInsert()
    {
        $connection = TestUtil::getConnection();

        self::assertSame('0', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertId()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdAfterUpdate()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->update('write_table', array('test_int' => 2), array('id' => 1));

        $this->assertSame('1', $connection->lastInsertId());

        $connection->close();

        return;

        $this->_conn->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $this->_conn->update('write_table', array('test_int' => 2), array('id' => 1));

        self::assertSame('1', $this->_conn->lastInsertId());
    }

    public function testLastInsertIdAfterDelete()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->exec('DELETE FROM write_table');

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdAfterTruncate()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->exec($connection->getDatabasePlatform()->getTruncateTableSQL('write_table'));

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdAfterDropTable()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $this->createTable('write_table_tmp');

        $connection->insert('write_table_tmp', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->getSchemaManager()->dropTable('write_table_tmp');

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdAfterSelect()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->executeQuery('SELECT 1 FROM write_table');

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdInTransaction()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->beginTransaction();
        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        self::assertSame('1', $connection->lastInsertId());
        $connection->rollBack();

        $connection->close();
    }

    public function testLastInsertIdAfterTransactionCommit()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->beginTransaction();
        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->commit();

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdAfterTransactionRollback()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->beginTransaction();
        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->rollBack();

        self::assertSame('1', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdInsertAfterTransactionRollback()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->beginTransaction();
        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->rollBack();
        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));

        $expected = $connection->getDatabasePlatform()->getName() === 'sqlite'
            // SQLite has a different transaction concept, that reuses rolled back IDs
            // See: http://sqlite.1065341.n5.nabble.com/Autoincrement-with-rollback-td79154.html
            ? '1'
            : '2';

        self::assertSame($expected, $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertIdConnectionScope()
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ($platform->getName() === 'sqlite') {
            $this->markTestSkipped('Test does not work on sqlite as connections do not share memory.');
        }

        if (! $platform->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection1 = TestUtil::getConnection();
        $connection2 = TestUtil::getConnection();

        $connection1->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));

        self::assertNotSame('1', $connection2->lastInsertId());

        $connection2->insert('write_table', array('test_int' => 2, 'test_string' => 'bar'));

        self::assertSame('1', $connection1->lastInsertId());
        self::assertSame('2', $connection2->lastInsertId());

        $connection1->close();
        $connection2->close();
    }

    public function testLastInsertIdSequence()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped('Test only works on platforms with sequences.');
        }

        $sequence = new \Doctrine\DBAL\Schema\Sequence('write_table_id_seq');
        try {
            $this->_conn->getSchemaManager()->createSequence($sequence);
        } catch(\Exception $e) {
        }

        $sequences = $this->_conn->getSchemaManager()->listSequences();
        self::assertCount(1, array_filter($sequences, function($sequence) {
            return strtolower($sequence->getName()) === 'write_table_id_seq';
        }));

        $stmt = $this->_conn->query($this->_conn->getDatabasePlatform()->getSequenceNextValSQL('write_table_id_seq'));
        $nextSequenceVal = $stmt->fetchColumn();

        $lastInsertId = $this->_conn->lastInsertId('write_table_id_seq');

        self::assertGreaterThan(0, $lastInsertId);
        self::assertEquals($nextSequenceVal, $lastInsertId);
    }

    /**
     * @group DBAL-445
     */
    public function testInsertWithKeyValueTypes()
    {
        $testString = new \DateTime('2013-04-14 10:10:10');

        $this->_conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $testString),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->_conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->_conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testUpdateWithKeyValueTypes()
    {
        $testString = new \DateTime('2013-04-14 10:10:10');

        $this->_conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $testString),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $testString = new \DateTime('2013-04-15 10:10:10');

        $this->_conn->update(
            'write_table',
            array('test_string' => $testString),
            array('test_int' => '30'),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->_conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->_conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testDeleteWithKeyValueTypes()
    {
        $val = new \DateTime('2013-04-14 10:10:10');
        $this->_conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $val),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $this->_conn->delete('write_table', array('test_int' => 30, 'test_string' => $val), array('test_string' => 'datetime', 'test_int' => 'integer'));

        $data = $this->_conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertFalse($data);
    }

    public function testEmptyIdentityInsert()
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ( ! ($platform->supportsIdentityColumns() || $platform->usesSequenceEmulatedIdentityColumns()) ) {
            $this->markTestSkipped(
                'Test only works on platforms with identity columns or sequence emulated identity columns.'
            );
        }

        $table = new Table('test_empty_identity');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        try {
            $this->_conn->getSchemaManager()->dropTable($table->getQuotedName($platform));
        } catch(\Exception $e) { }

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->_conn->exec($sql);
        }

        $seqName = $platform->usesSequenceEmulatedIdentityColumns()
            ? $platform->getIdentitySequenceName('test_empty_identity', 'id')
            : null;

        $sql = $platform->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->_conn->exec($sql);

        $firstId = $this->_conn->lastInsertId($seqName);

        $this->_conn->exec($sql);

        $secondId = $this->_conn->lastInsertId($seqName);

        self::assertGreaterThan($firstId, $secondId);

    }

    /**
     * @group DBAL-2688
     */
    public function testUpdateWhereIsNull()
    {
        $this->_conn->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->_conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->_conn->update('write_table', ['test_int' => 10], ['test_string' => null], ['test_string' => 'string', 'test_int' => 'integer']);

        $data = $this->_conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    public function testDeleteWhereIsNull()
    {
        $this->_conn->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->_conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->_conn->delete('write_table', ['test_string' => null], ['test_string' => 'string']);

        $data = $this->_conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }
}

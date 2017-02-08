<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\TestUtil;
use PDO;

class WriteTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->createTable('write_table');
    }

    private function createTable($tableName)
    {
        $table = new \Doctrine\DBAL\Schema\Table($tableName);
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', array('notnull' => false));
        $table->setPrimaryKey(array('id'));

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
        $this->_conn->executeUpdate($sql, array("text", 1111), array(null, PDO::PARAM_INT));

        $sql = "SELECT * FROM write_table WHERE test_string = ? AND test_int = ?";
        $this->assertTrue((bool)$this->_conn->fetchColumn($sql, array("text", 1111)));
    }

    public function testExecuteUpdate()
    {
        $sql = "INSERT INTO write_table (test_int) VALUES ( " . $this->_conn->quote(1) . ")";
        $affected = $this->_conn->executeUpdate($sql);

        $this->assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testExecuteUpdateWithTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $affected = $this->_conn->executeUpdate($sql, array(1, 'foo'), array(\PDO::PARAM_INT, \PDO::PARAM_STR));

        $this->assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testPrepareRowCountReturnsAffectedRows()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, "foo");
        $stmt->execute();

        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithPdoTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, \PDO::PARAM_INT);
        $stmt->bindValue(2, "foo", \PDO::PARAM_STR);
        $stmt->execute();

        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType('integer'));
        $stmt->bindValue(2, "foo", Type::getType('string'));
        $stmt->execute();

        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypeNames()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, "foo", 'string');
        $stmt->execute();

        $this->assertEquals(1, $stmt->rowCount());
    }

    public function insertRows()
    {
        $this->assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 1, 'test_string' => 'foo')));
        $this->assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
    }

    public function testInsert()
    {
        $this->insertRows();
    }

    public function testDelete()
    {
        $this->insertRows();

        $this->assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 2)));
        $this->assertEquals(1, count($this->_conn->fetchAll('SELECT * FROM write_table')));

        $this->assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 1)));
        $this->assertEquals(0, count($this->_conn->fetchAll('SELECT * FROM write_table')));
    }

    public function testUpdate()
    {
        $this->insertRows();

        $this->assertEquals(1, $this->_conn->update('write_table', array('test_string' => 'bar'), array('test_string' => 'foo')));
        $this->assertEquals(2, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
        $this->assertEquals(0, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
    }

    public function testLastInsertIdNoInsert()
    {
        $connection = TestUtil::getConnection();

        $this->assertSame('0', $connection->lastInsertId());

        $connection->close();
    }

    public function testLastInsertId()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame('1', $this->_conn->lastInsertId());
    }

    public function testLastInsertIdAfterDelete()
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection = TestUtil::getConnection();

        $connection->insert('write_table', array('test_int' => 1, 'test_string' => 'foo'));
        $connection->exec('DELETE FROM write_table');

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame('1', $connection->lastInsertId());

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
        $this->assertSame('1', $connection->lastInsertId());
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

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame('1', $connection->lastInsertId());

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

        $this->assertSame($expected, $connection->lastInsertId());

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

        $this->assertNotSame('1', $connection2->lastInsertId());

        $connection2->insert('write_table', array('test_int' => 2, 'test_string' => 'bar'));

        $this->assertSame('1', $connection1->lastInsertId());
        $this->assertSame('2', $connection2->lastInsertId());

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
        $this->assertEquals(1, count(array_filter($sequences, function($sequence) {
            return strtolower($sequence->getName()) === 'write_table_id_seq';
        })));

        $stmt = $this->_conn->query($this->_conn->getDatabasePlatform()->getSequenceNextValSQL('write_table_id_seq'));
        $nextSequenceVal = $stmt->fetchColumn();

        $lastInsertId = $this->_conn->lastInsertId('write_table_id_seq');

        $this->assertTrue($lastInsertId > 0);
        $this->assertEquals($nextSequenceVal, $lastInsertId);
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

        $this->assertEquals($testString->format($this->_conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
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

        $this->assertEquals($testString->format($this->_conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
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

        $this->assertFalse($data);
    }

    public function testEmptyIdentityInsert()
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ( ! ($platform->supportsIdentityColumns() || $platform->usesSequenceEmulatedIdentityColumns()) ) {
            $this->markTestSkipped(
                'Test only works on platforms with identity columns or sequence emulated identity columns.'
            );
        }

        $table = new \Doctrine\DBAL\Schema\Table('test_empty_identity');
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

        $this->assertTrue($secondId > $firstId);

    }

}

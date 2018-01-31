<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

class WriteTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("write_table");
            $table->addColumn('id', 'integer', array('autoincrement' => true));
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string', array('notnull' => false));
            $table->setPrimaryKey(array('id'));

            $this->conn->getSchemaManager()->createTable($table);
        } catch(\Exception $e) {

        }
        $this->conn->executeUpdate('DELETE FROM write_table');
    }

    /**
     * @group DBAL-80
     */
    public function testExecuteUpdateFirstTypeIsNull()
    {
        $sql = "INSERT INTO write_table (test_string, test_int) VALUES (?, ?)";
        $this->conn->executeUpdate($sql, array("text", 1111), array(null, ParameterType::INTEGER));

        $sql = "SELECT * FROM write_table WHERE test_string = ? AND test_int = ?";
        self::assertTrue((bool)$this->conn->fetchColumn($sql, array("text", 1111)));
    }

    public function testExecuteUpdate()
    {
        $sql = "INSERT INTO write_table (test_int) VALUES ( " . $this->conn->quote(1) . ")";
        $affected = $this->conn->executeUpdate($sql);

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testExecuteUpdateWithTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $affected = $this->conn->executeUpdate(
            $sql,
            array(1, 'foo'),
            array(ParameterType::INTEGER, ParameterType::STRING)
        );

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testPrepareRowCountReturnsAffectedRows()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, "foo");
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithPdoTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, "foo", ParameterType::STRING);
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypes()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType('integer'));
        $stmt->bindValue(2, "foo", Type::getType('string'));
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypeNames()
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, "foo", 'string');
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function insertRows()
    {
        self::assertEquals(1, $this->conn->insert('write_table', array('test_int' => 1, 'test_string' => 'foo')));
        self::assertEquals(1, $this->conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
    }

    public function testInsert()
    {
        $this->insertRows();
    }

    public function testDelete()
    {
        $this->insertRows();

        self::assertEquals(1, $this->conn->delete('write_table', array('test_int' => 2)));
        self::assertCount(1, $this->conn->fetchAll('SELECT * FROM write_table'));

        self::assertEquals(1, $this->conn->delete('write_table', array('test_int' => 1)));
        self::assertCount(0, $this->conn->fetchAll('SELECT * FROM write_table'));
    }

    public function testUpdate()
    {
        $this->insertRows();

        self::assertEquals(1, $this->conn->update('write_table', array('test_string' => 'bar'), array('test_string' => 'foo')));
        self::assertEquals(2, $this->conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
        self::assertEquals(0, $this->conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
    }

    public function testLastInsertId()
    {
        if ( ! $this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        self::assertEquals(1, $this->conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
        $num = $this->conn->lastInsertId();

        self::assertNotNull($num, "LastInsertId() should not be null.");
        self::assertGreaterThan(0, $num, "LastInsertId() should be non-negative number.");
    }

    public function testLastInsertIdSequence()
    {
        if ( ! $this->conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped('Test only works on platforms with sequences.');
        }

        $sequence = new \Doctrine\DBAL\Schema\Sequence('write_table_id_seq');
        try {
            $this->conn->getSchemaManager()->createSequence($sequence);
        } catch(\Exception $e) {
        }

        $sequences = $this->conn->getSchemaManager()->listSequences();
        self::assertCount(1, array_filter($sequences, function($sequence) {
            return strtolower($sequence->getName()) === 'write_table_id_seq';
        }));

        $stmt = $this->conn->query($this->conn->getDatabasePlatform()->getSequenceNextValSQL('write_table_id_seq'));
        $nextSequenceVal = $stmt->fetchColumn();

        $lastInsertId = $this->conn->lastInsertId('write_table_id_seq');

        self::assertGreaterThan(0, $lastInsertId);
        self::assertEquals($nextSequenceVal, $lastInsertId);
    }

    public function testLastInsertIdNoSequenceGiven()
    {
        if ( ! $this->conn->getDatabasePlatform()->supportsSequences() || $this->conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped("Test only works consistently on platforms that support sequences and don't support identity columns.");
        }

        self::assertFalse($this->conn->lastInsertId( null ));

    }

    /**
     * @group DBAL-445
     */
    public function testInsertWithKeyValueTypes()
    {
        $testString = new \DateTime('2013-04-14 10:10:10');

        $this->conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $testString),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testUpdateWithKeyValueTypes()
    {
        $testString = new \DateTime('2013-04-14 10:10:10');

        $this->conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $testString),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $testString = new \DateTime('2013-04-15 10:10:10');

        $this->conn->update(
            'write_table',
            array('test_string' => $testString),
            array('test_int' => '30'),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertEquals($testString->format($this->conn->getDatabasePlatform()->getDateTimeFormatString()), $data);
    }

    /**
     * @group DBAL-445
     */
    public function testDeleteWithKeyValueTypes()
    {
        $val = new \DateTime('2013-04-14 10:10:10');
        $this->conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => $val),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $this->conn->delete('write_table', array('test_int' => 30, 'test_string' => $val), array('test_string' => 'datetime', 'test_int' => 'integer'));

        $data = $this->conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        self::assertFalse($data);
    }

    public function testEmptyIdentityInsert()
    {
        $platform = $this->conn->getDatabasePlatform();

        if ( ! ($platform->supportsIdentityColumns() || $platform->usesSequenceEmulatedIdentityColumns()) ) {
            $this->markTestSkipped(
                'Test only works on platforms with identity columns or sequence emulated identity columns.'
            );
        }

        $table = new \Doctrine\DBAL\Schema\Table('test_empty_identity');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        try {
            $this->conn->getSchemaManager()->dropTable($table->getQuotedName($platform));
        } catch(\Exception $e) { }

        foreach ($platform->getCreateTableSQL($table) as $sql) {
            $this->conn->exec($sql);
        }

        $seqName = $platform->usesSequenceEmulatedIdentityColumns()
            ? $platform->getIdentitySequenceName('test_empty_identity', 'id')
            : null;

        $sql = $platform->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->conn->exec($sql);

        $firstId = $this->conn->lastInsertId($seqName);

        $this->conn->exec($sql);

        $secondId = $this->conn->lastInsertId($seqName);

        self::assertGreaterThan($firstId, $secondId);

    }

    /**
     * @group DBAL-2688
     */
    public function testUpdateWhereIsNull()
    {
        $this->conn->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->conn->update('write_table', ['test_int' => 10], ['test_string' => null], ['test_string' => 'string', 'test_int' => 'integer']);

        $data = $this->conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }

    public function testDeleteWhereIsNull()
    {
        $this->conn->insert(
            'write_table',
            ['test_int' => '30', 'test_string' => null],
            ['test_string' => 'string', 'test_int' => 'integer']
        );

        $data = $this->conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(1, $data);

        $this->conn->delete('write_table', ['test_string' => null], ['test_string' => 'string']);

        $data = $this->conn->fetchAll('SELECT * FROM write_table WHERE test_int = 30');

        self::assertCount(0, $data);
    }
}

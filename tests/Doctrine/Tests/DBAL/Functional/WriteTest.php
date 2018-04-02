<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;

class WriteTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->createTable('write_table');
    }

    private function createTable(string $tableName) : void
    {
        $table = new Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->_conn->getSchemaManager()->createTable($table);
    }

    protected function tearDown() : void
    {
        $this->_conn->getSchemaManager()->dropTable('write_table');

        parent::tearDown();
    }

    /**
     * @group DBAL-80
     */
    public function testExecuteUpdateFirstTypeIsNull() : void
    {
        $sql = "INSERT INTO write_table (test_string, test_int) VALUES (?, ?)";
        $this->_conn->executeUpdate($sql, ['text', 1111], [null, ParameterType::INTEGER]);

        $sql = "SELECT * FROM write_table WHERE test_string = ? AND test_int = ?";
        self::assertTrue((bool) $this->_conn->fetchColumn($sql, ['text', 1111]));
    }

    public function testExecuteUpdate() : void
    {
        $sql = "INSERT INTO write_table (test_int) VALUES ( " . $this->_conn->quote(1) . ")";
        $affected = $this->_conn->executeUpdate($sql);

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testExecuteUpdateWithTypes() : void
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $affected = $this->_conn->executeUpdate(
            $sql,
            [1, 'foo'],
            [ParameterType::INTEGER, ParameterType::STRING]
        );

        self::assertEquals(1, $affected, "executeUpdate() should return the number of affected rows!");
    }

    public function testPrepareRowCountReturnsAffectedRows() : void
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1);
        $stmt->bindValue(2, "foo");
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithPdoTypes() : void
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->bindValue(2, 'foo', ParameterType::STRING);
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypes() : void
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, Type::getType('integer'));
        $stmt->bindValue(2, "foo", Type::getType('string'));
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function testPrepareWithDbalTypeNames() : void
    {
        $sql = "INSERT INTO write_table (test_int, test_string) VALUES (?, ?)";
        $stmt = $this->_conn->prepare($sql);

        $stmt->bindValue(1, 1, 'integer');
        $stmt->bindValue(2, "foo", 'string');
        $stmt->execute();

        self::assertEquals(1, $stmt->rowCount());
    }

    public function insertRows() : void
    {
        self::assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 1, 'test_string' => 'foo')));
        self::assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
    }

    public function testInsert() : void
    {
        $this->insertRows();
    }

    public function testDelete() : void
    {
        $this->insertRows();

        self::assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 2)));
        self::assertCount(1, $this->_conn->fetchAll('SELECT * FROM write_table'));

        self::assertEquals(1, $this->_conn->delete('write_table', array('test_int' => 1)));
        self::assertCount(0, $this->_conn->fetchAll('SELECT * FROM write_table'));
    }

    public function testUpdate() : void
    {
        $this->insertRows();

        self::assertEquals(1, $this->_conn->update('write_table', array('test_string' => 'bar'), array('test_string' => 'foo')));
        self::assertEquals(2, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
        self::assertEquals(0, $this->_conn->update('write_table', array('test_string' => 'baz'), array('test_string' => 'bar')));
    }

    /**
     * @group DBAL-445
     */
    public function testInsertWithKeyValueTypes() : void
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
    public function testUpdateWithKeyValueTypes() : void
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
    public function testDeleteWithKeyValueTypes() : void
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

    public function testEmptyIdentityInsert() : void
    {
        $table = new Table('test_empty_identity');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);

        $sql = $this->_conn->getDatabasePlatform()->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        self::assertSame(1, $this->_conn->exec($sql));
    }

    /**
     * @group DBAL-2688
     */
    public function testUpdateWhereIsNull() : void
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

    public function testDeleteWhereIsNull() : void
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

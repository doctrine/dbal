<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Types\Type;
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
        $table = new \Doctrine\DBAL\Schema\Table('test_empty_identity');
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);

        $sql = $this->_conn->getDatabasePlatform()->getEmptyIdentityInsertSQL('test_empty_identity', 'id');

        $this->assertSame(1, $this->_conn->exec($sql));

    }

}

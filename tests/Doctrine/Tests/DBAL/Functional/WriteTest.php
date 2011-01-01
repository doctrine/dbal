<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Types\Type;
use PDO;

require_once __DIR__ . '/../../TestInit.php';

class WriteTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("write_table");
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string', array('notnull' => false));

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch(\Exception $e) {

        }
        $this->_conn->executeUpdate('DELETE FROM write_table');
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
        $this->assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 1)));
        $this->assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 2)));
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

        $this->assertEquals(1, $this->_conn->update('write_table', array('test_int' => 2), array('test_int' => 1)));
        $this->assertEquals(2, $this->_conn->update('write_table', array('test_int' => 3), array('test_int' => 2)));
    }
}
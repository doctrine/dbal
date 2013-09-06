<?php

namespace Doctrine\Tests\DBAL\Functional;
use Doctrine\DBAL\Types\Type;
use PDO;

class WriteTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("write_table");
            $table->addColumn('id', 'integer', array('autoincrement' => true));
            $table->addColumn('test_int', 'integer');
            $table->addColumn('test_string', 'string', array('notnull' => false));
            $table->setPrimaryKey(array('id'));

            foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) AS $sql) {
                $this->_conn->executeQuery($sql);
            }
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

    public function testLastInsertId()
    {
        if ( ! $this->_conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->assertEquals(1, $this->_conn->insert('write_table', array('test_int' => 2, 'test_string' => 'bar')));
        $num = $this->_conn->lastInsertId();

        $this->assertNotNull($num, "LastInsertId() should not be null.");
        $this->assertTrue($num > 0, "LastInsertId() should be non-negative number.");
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

    public function testLastInsertIdNoSequenceGiven()
    {
        if ( ! $this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped('Test only works on platforms with sequences.');
        }

        $this->assertFalse($this->_conn->lastInsertId( null ));

    }

    /**
     * @group DBAL-445
     */
    public function testInsertWithKeyValueTypes()
    {
        $this->_conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => new \DateTime('2013-04-14 10:10:10')),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->_conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        $this->assertEquals('2013-04-14 10:10:10', $data);
    }

    /**
     * @group DBAL-445
     */
    public function testUpdateWithKeyValueTypes()
    {
        $this->_conn->insert(
            'write_table',
            array('test_int' => '30', 'test_string' => new \DateTime('2013-04-14 10:10:10')),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $this->_conn->update(
            'write_table',
            array('test_string' => new \DateTime('2013-04-15 10:10:10')),
            array('test_int' => '30'),
            array('test_string' => 'datetime', 'test_int' => 'integer')
        );

        $data = $this->_conn->fetchColumn('SELECT test_string FROM write_table WHERE test_int = 30');

        $this->assertEquals('2013-04-15 10:10:10', $data);
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
}

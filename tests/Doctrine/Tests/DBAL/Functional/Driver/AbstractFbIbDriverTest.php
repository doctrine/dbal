<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

class AbstractFbIbDriverTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();
        
        if ( ! $this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\AbstractFbIbDriver) {
            $this->markTestSkipped('AbstractFbIbDriver connection only test.');
       }

        if ($this->_conn->getSchemaManager()->tablesExist('TEST_FBIB_DRIVER_TRNS')) {
            $this->_conn->executeQuery('DELETE FROM TEST_FBIB_DRIVER_TRNS');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('TEST_FBIB_DRIVER_TRNS');
            $table->addColumn('id', 'integer');
            $table->addColumn('item_value', 'integer');
            $table->setPrimaryKey(array('id'));

            $this->_conn->getSchemaManager()->createTable($table);
        }
    }

    public function testStatementRollback()
    {
        $stmt = $this->_conn->prepare('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (1, 1)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->rollback();

        $this->assertEquals(0, $this->_conn->query('SELECT COUNT(*) FROM TEST_FBIB_DRIVER_TRNS')->fetchColumn());
    }

    public function testStatementCommit()
    {
        $stmt = $this->_conn->prepare('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (1, 1)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->commit();

        $this->assertEquals(1, $this->_conn->query('SELECT COUNT(*) FROM TEST_FBIB_DRIVER_TRNS')->fetchColumn());
    }
    
    public function testMultipleRollbacksAndCommits()
    {
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(1, 1));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(2, 1));
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(3, 1));
        $this->_conn->commit();
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(4, 0));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(5, 0));
        $this->_conn->rollback();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(4, 1));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(5, 1));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(6, 1));
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(7, 0));
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(8, 0));
        $this->_conn->rollback();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(9, 0));
        $this->_conn->rollback();
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(7, 1));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(8, 1));
        $this->_conn->beginTransaction();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(9, 1));
        $this->_conn->commit();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(10, 1));
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(11, 1));
        $this->_conn->commit();
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(12, 1));
        $this->assertEquals(12, $this->_conn->query('SELECT COUNT(*) FROM TEST_FBIB_DRIVER_TRNS')->fetchColumn());
    }
    
    public function testStatementNamedParameters()
    {
        $stmt = $this->_conn->prepare('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (1, 999)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->commit();
        
        $qryStm = $this->_conn->prepare('select item_value from TEST_FBIB_DRIVER_TRNS where id=:lookfor_id and item_value=:lookfor_item_value');
        $qryStm->bindValue(':lookfor_id', 1);
        $qryStm->bindValue(':lookfor_item_value', 999);
        $qryStm->execute();
        $this->assertEquals(999, $qryStm->fetchColumn());
    }
    
    public function testStatementNamedParameters2()
    {
        $stmt = $this->_conn->prepare('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (1, 999)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->commit();
        
        $qryStm = $this->_conn->prepare('select item_value from TEST_FBIB_DRIVER_TRNS where '
                . '(id=:lookfor_id or id=:lookfor_item_value) and '
                . '(item_value=:lookfor_item_value)');
        $qryStm->bindValue(':lookfor_id', 1);
        $qryStm->bindValue(':lookfor_item_value', 999);
        $qryStm->execute();
        $this->assertEquals(999, $qryStm->fetchColumn());
    }
    
    public function testStatementPositionalParameters()
    {
        $stmt = $this->_conn->prepare('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (1, 999)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->commit();
        
        $qryStm = $this->_conn->prepare('select item_value from TEST_FBIB_DRIVER_TRNS where id=? and item_value=?');
        $qryStm->bindValue(1, 1);
        $qryStm->bindValue(2, 999);
        $qryStm->execute();
        $this->assertEquals(999, $qryStm->fetchColumn());
    }
    
    
    public function testClearParametersAfterExecution()
    {
        $this->_conn->executeUpdate('INSERT INTO TEST_FBIB_DRIVER_TRNS VALUES (?, ?)', array(1, 777));
        $qryStm = $this->_conn->prepare('select item_value from TEST_FBIB_DRIVER_TRNS where id=:lookfor_id');
        $qryStm->bindValue(':lookfor_id', 1);
        $qryStm->execute();
        $this->assertEquals(777, $qryStm->fetchColumn());
        $qryStm->execute();
        $this->assertEquals(777, $qryStm->fetchColumn());
    }
    
    
}

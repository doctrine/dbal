<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

/**
 * @group DBAL-202
 */
class DBAL202Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->conn->getDatabasePlatform()->getName() != 'oracle') {
            $this->markTestSkipped('OCI8 only test');
        }

        if ($this->conn->getSchemaManager()->tablesExist('DBAL202')) {
            $this->conn->exec('DELETE FROM DBAL202');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('DBAL202');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(array('id'));

            $this->conn->getSchemaManager()->createTable($table);
        }
    }

    public function testStatementRollback()
    {
        $stmt = $this->conn->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->conn->beginTransaction();
        $stmt->execute();
        $this->conn->rollBack();

        self::assertEquals(0, $this->conn->query('SELECT COUNT(1) FROM DBAL202')->fetchColumn());
    }

    public function testStatementCommit()
    {
        $stmt = $this->conn->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->conn->beginTransaction();
        $stmt->execute();
        $this->conn->commit();

        self::assertEquals(1, $this->conn->query('SELECT COUNT(1) FROM DBAL202')->fetchColumn());
    }
}

<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-202
 */
class DBAL202Test extends DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform()->getName() !== 'oracle') {
            $this->markTestSkipped('OCI8 only test');
        }

        if ($this->connection->getSchemaManager()->tablesExist('DBAL202')) {
            $this->connection->exec('DELETE FROM DBAL202');
        } else {
            $table = new Table('DBAL202');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(['id']);

            $this->connection->getSchemaManager()->createTable($table);
        }
    }

    public function testStatementRollback()
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->execute();
        $this->connection->rollBack();

        self::assertEquals(0, $this->connection->query('SELECT COUNT(1) FROM DBAL202')->fetchColumn());
    }

    public function testStatementCommit()
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->execute();
        $this->connection->commit();

        self::assertEquals(1, $this->connection->query('SELECT COUNT(1) FROM DBAL202')->fetchColumn());
    }
}

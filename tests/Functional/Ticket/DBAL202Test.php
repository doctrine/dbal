<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/**
 * @group DBAL-202
 */
class DBAL202Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDatabasePlatform()->getName() !== 'oracle') {
            self::markTestSkipped('OCI8 only test');
        }

        if ($this->connection->getSchemaManager()->tableExists('DBAL202')) {
            $this->connection->exec('DELETE FROM DBAL202');
        } else {
            $table = new Table('DBAL202');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(['id']);

            $this->connection->getSchemaManager()->createTable($table);
        }
    }

    public function testStatementRollback(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->execute();
        $this->connection->rollBack();

        self::assertEquals(0, $this->connection->query('SELECT COUNT(1) FROM DBAL202')->fetchOne());
    }

    public function testStatementCommit(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->execute();
        $this->connection->commit();

        self::assertEquals(1, $this->connection->query('SELECT COUNT(1) FROM DBAL202')->fetchOne());
    }
}

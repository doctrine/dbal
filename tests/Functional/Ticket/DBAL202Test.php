<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class DBAL202Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestSkipped('Oracle only test');
        }

        if ($this->connection->getSchemaManager()->tablesExist('DBAL202')) {
            $this->connection->executeStatement('DELETE FROM DBAL202');
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

        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(1) FROM DBAL202'));
    }

    public function testStatementCommit(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->execute();
        $this->connection->commit();

        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(1) FROM DBAL202'));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class DBAL202Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestSkipped('Oracle only test');
        }

        if ($this->connection->createSchemaManager()->tableExists('DBAL202')) {
            $this->connection->executeStatement('DELETE FROM DBAL202');
        } else {
            $table = Table::editor()
                ->setUnquotedName('DBAL202')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                )
                ->setPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()
                        ->setUnquotedColumnNames('id')
                        ->create(),
                )
                ->create();

            $this->connection->createSchemaManager()->createTable($table);
        }
    }

    public function testStatementRollback(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->executeStatement();
        $this->connection->rollBack();

        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(1) FROM DBAL202'));
    }

    public function testStatementCommit(): void
    {
        $stmt = $this->connection->prepare('INSERT INTO DBAL202 VALUES (8)');
        $this->connection->beginTransaction();
        $stmt->executeStatement();
        $this->connection->commit();

        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(1) FROM DBAL202'));
    }
}

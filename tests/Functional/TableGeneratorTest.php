<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Id\TableGenerator;
use Doctrine\DBAL\Id\TableGeneratorSchemaVisitor;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class TableGeneratorTest extends FunctionalTestCase
{
    private TableGenerator $generator;

    protected function setUp(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('TableGenerator does not work with SQLite');
        }

        $this->dropTableIfExists('sequences');

        $schema  = new Schema();
        $visitor = new TableGeneratorSchemaVisitor();
        $schema->visit($visitor);

        foreach ($schema->toSql($platform) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->generator = new TableGenerator($this->connection);
    }

    public function testNextVal(): void
    {
        $id1 = $this->generator->nextValue('tbl1');
        $id2 = $this->generator->nextValue('tbl1');
        $id3 = $this->generator->nextValue('tbl2');

        self::assertGreaterThan(0, $id1, 'First id has to be larger than 0');
        self::assertEquals($id1 + 1, $id2, 'Second id is one larger than first one.');
        self::assertEquals($id1, $id3, 'First ids from different tables are equal.');
    }

    public function testNextValNotAffectedByOuterTransactions(): void
    {
        $this->connection->beginTransaction();
        $id1 = $this->generator->nextValue('tbl1');
        $this->connection->rollBack();
        $id2 = $this->generator->nextValue('tbl1');

        self::assertGreaterThan(0, $id1, 'First id has to be larger than 0');
        self::assertEquals($id1 + 1, $id2, 'Second id is one larger than first one.');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ForeignKeyTest extends FunctionalTestCase
{
    public function testCreatingATableWithAForeignKey(): void
    {
        $schema = new Schema();

        $referencedTable = $schema->createTable('referenced_table');
        $referencedTable->addColumn('id', 'integer');
        $referencedTable->setPrimaryKey(['id']);

        $referencingTable = $schema->createTable('referencing_table');
        $referencingTable->addColumn('referenced_id', 'integer');
        $referencingTable->addForeignKeyConstraint(
            $referencedTable,
            ['referenced_id'],
            ['id']
        );

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        self::assertCount(
            1,
            $this->connection->getSchemaManager()->listTableForeignKeys('referencing_table')
        );
    }
}

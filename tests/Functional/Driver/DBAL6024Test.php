<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

class DBAL6024Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql or the pgsql driver.');
    }

    public function testDropPrimaryKey(): void
    {
        $table = new Table('mytable', [
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
        ]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id')
                ->create(),
        );
        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $table = $schemaManager->introspectTable('mytable');

        $newTable = clone $table;
        $newTable->dropPrimaryKey();

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);

        $schemaManager->alterTable($diff);

        $validationSchema = $schemaManager->introspectSchema();
        $validationTable  = $validationSchema->getTable($table->getName());

        self::assertNull($validationTable->getPrimaryKeyConstraint());
    }
}

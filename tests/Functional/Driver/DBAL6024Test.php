<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Override;

class DBAL6024Test extends FunctionalTestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql or the pgsql driver.');
    }

    public function testDropPrimaryKey(): void
    {
        $table = Table::editor()
            ->setUnquotedName('mytable')
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

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        $table = $schemaManager->introspectTableByUnquotedName('mytable');

        $newTable = $table->edit()
            ->dropPrimaryKeyConstraint()
            ->create();

        $diff = $schemaManager->createComparator()->compareTables($table, $newTable);

        $schemaManager->alterTable($diff);

        $validationSchema = $schemaManager->introspectSchema();
        $validationTable  = $validationSchema->getTable($table->getObjectName()->toString());

        self::assertNull($validationTable->getPrimaryKeyConstraint());
    }
}

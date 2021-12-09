<?php

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function count;

final class NewPrimaryKeyWithNewAutoIncrementColumnTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->getPlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        self::markTestSkipped('Restricted to MySQL.');
    }

    /**
     * Ensures that the primary key is created within the same "alter table" statement that an auto-increment column
     * is added to the table as part of the new primary key.
     *
     * Before the fix for this problem this resulted in a database error: (at least on mysql)
     * SQLSTATE[42000]: Syntax error or access violation: 1075 Incorrect table definition; there can be only one auto
     * column and it must be defined as a key
     *
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     *
     * @dataProvider \Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils::comparatorProvider
     */
    public function testAlterPrimaryKeyToAutoIncrementColumn(callable $comparatorFactory): void
    {
        $this->dropTableIfExists('dbal2807');

        $schemaManager = $this->connection->getSchemaManager();
        $schema        = $schemaManager->createSchema();

        $table = $schema->createTable('dbal2807');
        $table->addColumn('initial_id', 'integer');
        $table->setPrimaryKey(['initial_id']);

        $schemaManager->createTable($table);

        $newSchema = clone $schema;
        $newTable  = $newSchema->getTable($table->getName());
        $newTable->addColumn('new_id', 'integer', ['autoincrement' => true]);
        $newTable->dropPrimaryKey();
        $newTable->setPrimaryKey(['new_id']);

        $diff = $comparatorFactory($schemaManager)
            ->compare($schema, $newSchema);

        $schemaManager->alterSchema($diff);

        $validationSchema = $schemaManager->createSchema();
        $validationTable  = $validationSchema->getTable($table->getName());

        self::assertTrue($validationTable->hasColumn('new_id'));
        self::assertTrue($validationTable->getColumn('new_id')->getAutoincrement());
        self::assertTrue($validationTable->hasPrimaryKey());
        self::assertEquals(1, count($validationTable->getPrimaryKeyColumns()));
        self::assertArrayHasKey('new_id', $validationTable->getPrimaryKeyColumns());
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}

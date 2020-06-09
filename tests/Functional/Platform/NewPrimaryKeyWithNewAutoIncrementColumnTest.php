<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Tests\FunctionalTestCase;

final class NewPrimaryKeyWithNewAutoIncrementColumnTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->getPlatform() instanceof MySqlPlatform) {
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
     */
    public function testAlterPrimaryKeyToAutoIncrementColumn(): void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $schema        = $schemaManager->createSchema();

        $table = $schema->createTable('dbal2807');
        $table->addColumn('initial_id', 'integer');
        $table->setPrimaryKey(['initial_id']);

        $schemaManager->dropAndCreateTable($table);

        $newSchema = clone $schema;
        $newTable  = $newSchema->getTable($table->getName());
        $newTable->addColumn('new_id', 'integer', ['autoincrement' => true]);
        $newTable->dropPrimaryKey();
        $newTable->setPrimaryKey(['new_id']);

        $diff = (new Comparator())->compare($schema, $newSchema);

        foreach ($diff->toSql($this->getPlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $validationSchema = $schemaManager->createSchema();
        $validationTable  = $validationSchema->getTable($table->getName());

        self::assertTrue($validationTable->hasColumn('new_id'));
        self::assertTrue($validationTable->getColumn('new_id')->getAutoincrement());
        self::assertTrue($validationTable->hasPrimaryKey());
        self::assertSame(['new_id'], $validationTable->getPrimaryKeyColumns());
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}

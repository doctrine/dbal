<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\Tests\DbalFunctionalTestCase;
use function in_array;

final class NewPrimaryKeyWithNewAutoIncrementColumnTest extends DbalFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        parent::setUp();

        if (in_array($this->getPlatform()->getName(), ['mysql'])) {
            return;
        }

        $this->markTestSkipped('Restricted to MySQL.');
    }

    /**
     * Ensures that the primary key is created within the same "alter table" statement that an auto-increment column
     * is added to the table as part of the new primary key.
     *
     * Before the fix for this problem this resulted in a database error: (at least on mysql)
     * SQLSTATE[42000]: Syntax error or access violation: 1075 Incorrect table definition; there can be only one auto
     * column and it must be defined as a key
     */
    public function testAlterPrimaryKeyToAutoIncrementColumn() : void
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

        $this->assertTrue($validationTable->hasColumn('new_id'));
        $this->assertTrue($validationTable->getColumn('new_id')->getAutoincrement());
        $this->assertTrue($validationTable->hasPrimaryKey());
        $this->assertSame(['new_id'], $validationTable->getPrimaryKeyColumns());
    }

    private function getPlatform() : AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}

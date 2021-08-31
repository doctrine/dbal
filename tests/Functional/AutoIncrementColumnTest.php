<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class AutoIncrementColumnTest extends FunctionalTestCase
{
    /** @var bool */
    private $shouldDisableIdentityInsert = false;

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('auto_increment_table');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()
            ->dropAndCreateTable($table);
    }

    protected function tearDown(): void
    {
        if ($this->shouldDisableIdentityInsert) {
            $this->connection->executeStatement('SET IDENTITY_INSERT auto_increment_table OFF');
        }

        parent::tearDown();
    }

    public function testInsertIdentityValue(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->connection->executeStatement('SET IDENTITY_INSERT auto_increment_table ON');
            $this->shouldDisableIdentityInsert = true;
        }

        $this->connection->insert('auto_increment_table', ['id' => 2]);
        self::assertEquals(2, $this->connection->fetchOne('SELECT id FROM auto_increment_table'));
    }
}

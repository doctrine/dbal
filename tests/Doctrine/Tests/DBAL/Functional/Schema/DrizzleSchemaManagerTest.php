<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BinaryType;

class DrizzleSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function testListTableWithBinary() : void
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', []);
        $table->addColumn('column_binary', 'binary', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->listTableDetails($tableName);

        self::assertInstanceOf(BinaryType::class, $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf(BinaryType::class, $table->getColumn('column_binary')->getType());
        self::assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testColumnCollation() : void
    {
        $table                                  = new Table('test_collation');
        $table->addOption('collate', $collation = 'utf8_unicode_ci');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'utf8_swedish_ci');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'utf8_general_ci');
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('utf8_unicode_ci', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('utf8_swedish_ci', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('utf8_general_ci', $columns['bar']->getPlatformOption('collation'));
    }
}

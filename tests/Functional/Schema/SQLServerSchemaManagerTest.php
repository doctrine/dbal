<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLServerPlatform;
    }

    public function testColumnCollation(): void
    {
        $table = new Table('test_collation', [
            Column::editor()
                ->setUnquotedName('test')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns('test_collation');

        // SQL Server should report a default collation on the column
        self::assertNotNull($columns['test']->getCollation());

        $table->getColumn('test')
            ->setPlatformOption('collation', 'Icelandic_CS_AS');

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertEquals('Icelandic_CS_AS', $columns['test']->getCollation());
    }

    public function testDefaultConstraints(): void
    {
        $oldTable = new Table('sqlsrv_default_constraints', [
            Column::editor()
                ->setUnquotedName('no_default')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->create(),
            Column::editor()
                ->setUnquotedName('df_integer')
                ->setTypeName(Types::INTEGER)
                ->setDefaultValue(666)
                ->create(),
            Column::editor()
                ->setUnquotedName('df_string_1')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setDefaultValue('foobar')
                ->create(),
            Column::editor()
                ->setUnquotedName('df_string_2')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setDefaultValue('Doctrine rocks!!!')
                ->create(),
            Column::editor()
                ->setUnquotedName('df_string_3')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setDefaultValue('another default value')
                ->create(),
            Column::editor()
                ->setUnquotedName('df_string_4')
                ->setTypeName(Types::STRING)
                ->setLength(32)
                ->setDefaultValue('column to rename')
                ->create(),
            Column::editor()
                ->setUnquotedName('df_boolean')
                ->setTypeName(Types::BOOLEAN)
                ->setDefaultValue(true)
                ->create(),
        ]);

        $newTable = clone $oldTable;

        $this->schemaManager->createTable($oldTable);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(666, $columns['df_integer']->getDefault());
        self::assertEquals('foobar', $columns['df_string_1']->getDefault());
        self::assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(1, $columns['df_boolean']->getDefault());

        $newTable->getColumn('df_integer')
            ->setDefault(0);

        $newTable->dropColumn('df_string_1');

        $newTable->getColumn('df_string_2')
            ->setDefault(null);

        $newTable->getColumn('df_boolean')
            ->setDefault(false);

        $newTable->dropColumn('df_string_4');
        $newTable->addColumn('df_string_4_renamed', Types::STRING, [
            'length' => 32,
            'default' => 'column to rename',
        ]);

        $diff = $this->schemaManager->createComparator()
            ->compareTables(
                $this->schemaManager->introspectTable('sqlsrv_default_constraints'),
                $newTable,
            );

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(0, $columns['df_integer']->getDefault());
        self::assertNull($columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(0, $columns['df_boolean']->getDefault());
        self::assertEquals('column to rename', $columns['df_string_4_renamed']->getDefault());
    }

    public function testNvarcharMaxIsLengthMinus1(): void
    {
        $sql = 'CREATE TABLE test_nvarchar_max (
            col_nvarchar_max NVARCHAR(MAX),
            col_nvarchar NVARCHAR(128)
        )';

        $this->connection->executeStatement($sql);

        $table = $this->schemaManager->introspectTable('test_nvarchar_max');

        self::assertSame(-1, $table->getColumn('col_nvarchar_max')->getLength());
        self::assertSame(128, $table->getColumn('col_nvarchar')->getLength());
    }

    /** @link https://learn.microsoft.com/en-us/sql/relational-databases/security/authentication-access/ownership-and-user-schema-separation?view=sql-server-ver16#the-dbo-schema */
    public function getExpectedDefaultSchemaName(): string
    {
        return 'dbo';
    }
}

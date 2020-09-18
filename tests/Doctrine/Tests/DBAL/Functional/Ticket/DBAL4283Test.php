<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * Fixes DBAL issue #4283: Doctrine fails to declare Extended Properties on SQL-Server when table name is quoted.
 *
 * The problem is in the SQLServerPlatform which incorrectly quotes the SCHEMA and TABLE object names
 * when they contain Reserved Words.
 *
 * The fix is to keep the SCHEMA and TABLE as identifiers in the T-SQL query.
 *
 * TODO: 1. Trigger the bug through {@see SQLServerPlatform::getAlterColumnCommentSQL}
 * TODO: 2. It's possible that the COLUMN object needs the same treatment.
 *
 * @link https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/basic-mapping.html#quoting-reserved-words
 * @link https://github.com/doctrine/dbal/issues/4283
 */
class DBAL4283Test extends DbalFunctionalTestCase
{
    /* @var Schema */
    private $schema;

    /* @var SQLServer2012Platform */
    private $platform;

    public function setUp(): void
    {
        parent::setUp();

        $this->platform = new SQLServer2012Platform();

        $driver        = $this->createStub(Driver::class);
        $connection    = new Connection([], $driver);
        $schemaManager = new SQLServerSchemaManager($connection, $this->platform);

        $metadataSchemaConfig = $schemaManager->createSchemaConfig();
        $metadataSchemaConfig->setExplicitForeignKeyIndexes(false);

        $this->schema = new Schema([], [], $metadataSchemaConfig);
    }

    /**
     * @dataProvider tableNameProvider
     */
    public function testAddExtendedPropertyToColumn(string $tableName, string $schemaSQL, string $tableSQL): void
    {
        self::assertEquals(
            sprintf(
                "EXEC sp_addextendedproperty N'MS_Description', N'(DC2Type:array)', "
                . "N'SCHEMA', %s, N'TABLE', %s, N'COLUMN', column_name",
                $schemaSQL,
                $tableSQL
            ),
            $this->getAddExtendedPropertyQuery($tableName)
        );
    }

    /**
     * @dataProvider tableNameProvider
     */
    public function testRemoveExtendedPropertyFromColumn(string $tableName, string $schemaSQL, string $tableSQL): void
    {
        self::assertEquals(
            sprintf(
                "EXEC sp_dropextendedproperty N'MS_Description', "
                . "N'SCHEMA', %s, N'TABLE', %s, N'COLUMN', column_name",
                $schemaSQL,
                $tableSQL
            ),
            $this->getDropExtendedPropertyQuery($tableName)
        );
    }

    /**
     * @dataProvider tableNameProvider
     */
    public function testUpdateExtendedPropertyFromColumn(string $tableName, $schemaSQL, string $tableSQL): void
    {
        self::assertEquals(
            sprintf(
                "EXEC sp_updateextendedproperty N'MS_Description', "
                . "N'SCHEMA', %s, N'TABLE', %s, N'COLUMN', column_name",
                $schemaSQL,
                $tableSQL
            ),
            $this->getUpdateExtendedPropertyQuery($tableName)
        );
    }

    public function getAddExtendedPropertyQuery(string $tableName): string
    {
        $table = $this->schema->createTable($tableName);
        $table->addColumn('column_name', 'array');

        return $this->platform->getCreateTableSQL($table)[1];
    }

    public function getDropExtendedPropertyQuery(string $tableName): string
    {
        // Change column type to something that needn't an Extended Property declaration.
        $table = $this->schema->createTable($tableName);
        $table->addColumn('column_name', 'array');

        $newColumn = new Column('column_name', Type::getType('string'));
        $tableDiff = new TableDiff($tableName, [], [
            new ColumnDiff($table->getName(), $newColumn, [], $table->getColumn('column_name'))
        ]);

        return $this->platform->getAlterTableSQL($tableDiff)[1];
    }

    public function getUpdateExtendedPropertyQuery(string $tableName): string
    {
        $table = $this->schema->createTable($tableName);
        $table->addColumn('column_name', 'array');

        $newColumn = new Column('column_name', Type::getType('json'));
        $tableDiff = new TableDiff($tableName, [], [
            new ColumnDiff($table->getName(), $newColumn, [], $table->getColumn('column_name'))
        ]);

        return $this->platform->getAlterTableSQL($tableDiff)[1];
    }

    public function tableNameProvider(): array
    {
        return [
            ['table_name', "'dbo'", "'table_name'"],
            ['`table-name-with-reserved-words`', "'dbo'", '[table-name-with-reserved-words]'],
            ['`custom.table-name-with-reserved-words`', '[custom]', '[table-name-with-reserved-words]'],
        ];
    }
}

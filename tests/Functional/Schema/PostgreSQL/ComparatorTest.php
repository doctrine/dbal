<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\PostgreSQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This test covers PostgreSQL-specific schema comparison scenarios.');
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /**
     * The PostgreSQL platform maps both BLOB and BINARY columns to the BYTEA column type.
     *
     * @see PostgreSQLPlatform::getBlobTypeDeclarationSQL()
     */
    public function testCompareBinaryAndBlob(): void
    {
        $this->testColumnModification(static function (ColumnEditor $editor): void {
            $editor->setTypeName(Types::BINARY);
        }, static function (ColumnEditor $editor): void {
            $editor->setTypeName(Types::BLOB);
        });
    }

    /**
     * The PostgreSQL platform maps both BINARY and VARBINARY columns to the BYTEA column type.
     *
     * @see PostgreSQLPlatform::getVarbinaryTypeDeclarationSQLSnippet()
     */
    public function testCompareBinaryAndVarbinary(): void
    {
        $this->testColumnModification(static function (ColumnEditor $editor): void {
            $editor->setTypeName(Types::BINARY);
        }, static function (ColumnEditor $editor): void {
            $editor->setFixed(true);
        });
    }

    /**
     * The PostgreSQL platform disregards the "length" attribute of BINARY and VARBINARY columns.
     *
     * @see PostgreSQLPlatform::getBinaryTypeDeclarationSQLSnippet()
     */
    public function testCompareBinariesOfDifferentLength(): void
    {
        $this->testColumnModification(static function (ColumnEditor $editor): void {
            $editor
                ->setTypeName(Types::BINARY)
                ->setLength(16);
        }, static function (ColumnEditor $editor): void {
            $editor->setLength(32);
        });
    }

    public function testPlatformOptionsChangedColumnComparison(): void
    {
        $table = Table::editor()
            ->setUnquotedName('update_json_to_jsonb_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName(Types::JSON)
                    ->create(),
            )
            ->create();

        $onlineTable = $table->edit()
            ->modifyColumnByUnquotedName('test', static function (ColumnEditor $editor): void {
                $editor->setTypeName(Types::JSONB);
            })
            ->create();

        $compareResult = $this->comparator->compareTables($onlineTable, $table);
        self::assertCount(1, $compareResult->getChangedColumns());
        self::assertCount(1, $compareResult->getModifiedColumns());

        $changedColumn = $compareResult->getChangedColumns()['test'];

        self::assertTrue($changedColumn->hasTypeChanged());
        self::assertEquals(1, $changedColumn->countChangedProperties());
    }

    /**
     * @param callable(ColumnEditor): void $initializeColumn
     * @param callable(ColumnEditor): void $modifyColumn
     */
    private function testColumnModification(callable $initializeColumn, callable $modifyColumn): void
    {
        $editor = Column::editor()
            ->setUnquotedName('id');

        $initializeColumn($editor);

        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns($editor->create())
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->modifyColumnByUnquotedName('id', $modifyColumn)
            ->create();

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $this->comparator,
            $table,
        )->isEmpty());
    }
}

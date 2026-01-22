<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Override;

class ColumnRenameTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    /** @throws Exception */
    public function testRenameColumnInIndex(): void
    {
        $this->testRenameColumn(static function (TableEditor $editor): void {
            $editor->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_c1_c2')
                    ->setUnquotedColumnNames('c1', 'c2')
                    ->create(),
            );
        });
    }

    /** @throws Exception */
    public function testRenameColumnInForeignKeyConstraint(): void
    {
        $this->dropTableIfExists('rename_column_referenced');

        $referencedTable = Table::editor()
            ->setUnquotedName('rename_column_referenced')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            // PostgreSQL requires a unique constraint on the referenced table columns
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('c1', 'c2')
                    ->create(),
            )
            ->create();

        $this->connection->createSchemaManager()->createTable($referencedTable);

        $this->testRenameColumn(static function (TableEditor $editor): void {
            $editor->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedName('fk_c1_c2')
                    ->setUnquotedReferencingColumnNames('c1', 'c2')
                    ->setUnquotedReferencedTableName('rename_column_referenced')
                    ->setUnquotedReferencedColumnNames('c1', 'c2')
                    ->create(),
            );
        });
    }

    /**
     * @param callable(TableEditor): void $modifier
     *
     * @throws Exception
     */
    private function testRenameColumn(callable $modifier): void
    {
        $this->dropTableIfExists('rename_column');

        $editor = Table::editor()
            ->setUnquotedName('rename_column')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('c2')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            );

        $modifier($editor);

        $table = $editor->create();

        $table->renameColumn('c1', 'c1a');

        $this->connection->createSchemaManager()->createTable($table);

        self::assertTrue($this->comparator->compareTables(
            $table,
            $this->schemaManager->introspectTableByUnquotedName('rename_column'),
        )->isEmpty());
    }
}

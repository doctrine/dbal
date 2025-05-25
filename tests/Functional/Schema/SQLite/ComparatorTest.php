<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\SQLite;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
    private AbstractPlatform $platform;

    private AbstractSchemaManager $schemaManager;

    private Comparator $comparator;

    protected function setUp(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof SQLitePlatform) {
            self::markTestSkipped();
        }

        $this->schemaManager = $this->connection->createSchemaManager();
        $this->comparator    = $this->schemaManager->createComparator();
    }

    public function testChangeTableCollation(): void
    {
        $table = Table::editor()
            ->setUnquotedName('comparator_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::STRING)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $table = $table->edit()
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor): void {
                $editor->setCollation('NOCASE');
            })
            ->create();

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }
}

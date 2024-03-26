<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\SQLite;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
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
        $table  = new Table('comparator_test');
        $column = $table->addColumn('id', Types::STRING);
        $this->dropAndCreateTable($table);

        $column->setPlatformOption('collation', 'NOCASE');
        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }

    public function testEnumDiffDetected(): void
    {
        $table = new Table('enum_test_table');

        $table->addColumn('enum_col', Types::ENUM, ['members' => ['a', 'b']]);
        $this->dropAndCreateTable($table);

        ComparatorTestUtils::assertNoDiffDetected($this->connection, $this->comparator, $table);

        // Alter column to previous state and check diff
        $sql = 'ALTER TABLE enum_test_table ALTER COLUMN enum_col enum_col ENUM(\'NOT_A_MEMBER_ANYMORE\') NOT NULL';
        $this->connection->executeStatement($sql);

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $this->comparator, $table);
    }
}

<?php

namespace Doctrine\DBAL\Tests\Functional\Schema\SQLite;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class ComparatorTest extends FunctionalTestCase
{
    /** @var AbstractPlatform */
    private $platform;

    /** @var AbstractSchemaManager */
    private $schemaManager;

    /** @var Comparator */
    private $comparator;

    protected function setUp(): void
    {
        $this->platform = $this->connection->getDatabasePlatform();

        if (! $this->platform instanceof SqlitePlatform) {
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
}

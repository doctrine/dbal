<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Schema\ComparatorTestUtils;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class JsonbTest extends FunctionalTestCase
{
    public function testJsonbColumnIntrospection(): void
    {
        $table = new Table('test_jsonb');
        $table->addColumn('v', Types::JSONB);

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $schemaManager,
            $comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $schemaManager,
            $comparator,
            $table,
        )->isEmpty());
    }
}

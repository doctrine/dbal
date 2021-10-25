<?php

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\Tools\TestAsset\SimpleEnum;
use Doctrine\DBAL\Types\Types;

/**
 * @requires PHP >= 8.1
 */
class EnumTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('enum_table');
        $table->addColumn('enum', 'enum');

        $this->connection->createSchemaManager()->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $draft = SimpleEnum::DRAFT;

        $result = $this->connection->insert('enum_table', ['enum' => $draft], [Types::ENUM]);
        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT enum FROM enum_table');

        self::assertSame($draft, $this->connection->convertToPHPValue($value, Types::ENUM));
    }
}

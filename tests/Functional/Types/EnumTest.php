<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class EnumTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('enum_table');
        $table->addColumn('val', Types::ENUM, ['members' => ['a', 'b']]);

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $val = 'b';

        $result = $this->connection->insert('enum_table', ['val' => $val]);
        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT val FROM enum_table');

        self::assertEquals($val, $value);
    }
}

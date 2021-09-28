<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;

final class DecimalTest extends DbalFunctionalTestCase
{
    public function testInsertAndRetrieveDecimal(): void
    {
        $table = new Table('decimal_table');
        $table->addColumn('val', Types::DECIMAL, ['precision' => 4, 'scale' => 2]);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);

        $this->connection->insert(
            'decimal_table',
            ['val' => '13.37'],
            ['val' => Types::DECIMAL]
        );

        $value = Type::getType(Types::DECIMAL)->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM decimal_table'),
            $this->connection->getDatabasePlatform()
        );

        self::assertSame('13.37', $value);
    }
}

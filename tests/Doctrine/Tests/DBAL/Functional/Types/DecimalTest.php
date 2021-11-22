<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;

use function rtrim;

final class DecimalTest extends DbalFunctionalTestCase
{
    /**
     * @return string[][]
     */
    public function dataValuesProvider(): array
    {
        return [
            ['13.37'],
            ['13.0'],
        ];
    }

    /**
     * @dataProvider dataValuesProvider
     */
    public function testInsertAndRetrieveDecimal(string $expected): void
    {
        $table = new Table('decimal_table');
        $table->addColumn('val', Types::DECIMAL, ['precision' => 4, 'scale' => 2]);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);

        $this->connection->insert(
            'decimal_table',
            ['val' => $expected],
            ['val' => Types::DECIMAL]
        );

        $value = Type::getType(Types::DECIMAL)->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM decimal_table'),
            $this->connection->getDatabasePlatform()
        );

        self::assertIsString($value);
        self::assertSame($this->stripTrailingZero($expected), $this->stripTrailingZero($value));
    }

    private function stripTrailingZero(string $expected): string
    {
        return rtrim(rtrim($expected, '0'), '.');
    }
}

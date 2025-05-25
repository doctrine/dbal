<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

class AsciiStringTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('ascii_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::ASCII_STRING)
                    ->setLength(3)
                    ->setFixed(true)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::ASCII_STRING)
                    ->setLength(4)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $id1 = 'id1';
        $id2 = 'id2';

        $value1 = 'val1';
        $value2 = 'val2';

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        self::assertSame($value1, $this->select($id1));
        self::assertSame($value2, $this->select($id2));
    }

    private function insert(string $id, string $value): void
    {
        $result = $this->connection->insert('ascii_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::ASCII,
            ParameterType::ASCII,
        ]);

        self::assertSame(1, $result);
    }

    private function select(string $id): string
    {
        $value = $this->connection->fetchOne(
            'SELECT val FROM ascii_table WHERE id = ?',
            [$id],
            [ParameterType::ASCII],
        );

        self::assertIsString($value);

        return $value;
    }
}

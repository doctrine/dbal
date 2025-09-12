<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\Platforms\MySQL90Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class VectorTypeTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();
        if (! $platform instanceof MariaDB110700Platform && ! $platform instanceof MySQL90Platform) {
            self::markTestSkipped('Vector type is only supported on MariaDB 11.7+ and MySQL 9.0+.');
        }

        $table = Table::editor()
            ->setUnquotedName('vector_test_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('my_vector')
                    ->setTypeName(Types::VECTOR)
                    ->setLength(3)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $this->insert(1, [0.1, 0.2, 0.3]);
        $this->insert(2, [47.11, 8.15, 3.14159]);

        self::assertEqualsWithDelta([0.1, 0.2, 0.3], $this->select(1), .00001);
        self::assertEqualsWithDelta([47.11, 8.15, 3.14159], $this->select(2), .00001);
    }

    /** @param list<float> $value */
    private function insert(int $id, array $value): void
    {
        $result = $this->connection->insert('vector_test_table', [
            'id'  => $id,
            'my_vector' => $value,
        ], [
            ParameterType::INTEGER,
            Types::VECTOR,
        ]);

        self::assertSame(1, $result);
    }

    /** @return list<float> */
    private function select(int $id): array
    {
        $value = $this->connection->fetchOne(
            'SELECT my_vector FROM vector_test_table WHERE id = ?',
            [$id],
            [ParameterType::INTEGER],
        );

        $convertedValue = $this->connection->convertToPHPValue($value, Types::VECTOR);
        self::assertIsList($convertedValue);

        return $convertedValue;
    }
}

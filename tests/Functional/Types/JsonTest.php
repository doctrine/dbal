<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function is_resource;
use function json_decode;
use function ksort;
use function stream_get_contents;

class JsonTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('json_test_table');
        $table->addColumn('id', Types::INTEGER);

        $table->addColumn('val', Types::JSON);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    public function testInsertAndSelect(): void
    {
        $id1 = 1;
        $id2 = 2;

        $value1 = [
            'firstKey' => 'firstVal',
            'secondKey' => 'secondVal',
            'nestedKey' => [
                'nestedKey1' => 'nestedVal1',
                'nestedKey2' => 2,
            ],
        ];
        $value2 = json_decode('{"key1":"Val1","key2":2,"key3":"Val3"}', true);

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        $res1 = $this->select($id1);
        $res2 = $this->select($id2);

        // The returned arrays are not guaranteed to be in the same order so sort them
        ksort($value1);
        ksort($value2);
        ksort($res1);
        ksort($res2);

        self::assertSame($value1, $res1);
        self::assertSame($value2, $res2);
    }

    /** @param array<scalar|array> $value */
    private function insert(int $id, array $value): void
    {
        $result = $this->connection->insert('json_test_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::INTEGER,
            Type::getType(Types::JSON),
        ]);

        self::assertSame(1, $result);
    }

    /** @return array<scalar|array> */
    private function select(int $id): array
    {
        $value = $this->connection->fetchOne(
            'SELECT val FROM json_test_table WHERE id = ?',
            [$id],
            [ParameterType::INTEGER],
        );

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        self::assertIsString($value);

        $value = json_decode($value, true);

        self::assertIsArray($value);

        return $value;
    }
}

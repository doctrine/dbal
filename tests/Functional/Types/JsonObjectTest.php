<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use stdClass;

use function is_resource;
use function json_decode;
use function stream_get_contents;

class JsonObjectTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('json_test_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('val')
                    ->setTypeName(Types::JSON_OBJECT)
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
        $id1 = 1;
        $id2 = 2;

        $value1                        = new stdClass();
        $value1->firstKey              = 'firstVal';
        $value1->secondKey             = 'secondVal';
        $value1->nestedKey             = new stdClass();
        $value1->nestedKey->nestedKey1 = 'nestedVal1';
        $value1->nestedKey->nestedKey2 = 2;

        $value2 = json_decode('{"key1":"Val1","key2":2,"key3":"Val3"}', false);

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        $res1 = $this->select($id1);
        $res2 = $this->select($id2);

        self::assertEquals($value1, $res1);
        self::assertEquals($value2, $res2);
    }

    private function insert(int $id, object $value): void
    {
        $result = $this->connection->insert('json_test_table', [
            'id'  => $id,
            'val' => $value,
        ], [
            ParameterType::INTEGER,
            Type::getType(Types::JSON_OBJECT),
        ]);

        self::assertSame(1, $result);
    }

    private function select(int $id): object
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

        $value = json_decode($value, false);

        self::assertIsObject($value);

        return $value;
    }
}

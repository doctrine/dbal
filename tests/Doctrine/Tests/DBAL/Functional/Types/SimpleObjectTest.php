<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\ArrayOf;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\Tests\Object\SimpleObject;
use Doctrine\Tests\Types\SimpleObjectType;

class SimpleObjectTest extends DbalFunctionalTestCase
{
    private const TEST_TABLE = 'simple_object_table';

    public function testInsertAndSelect(): void
    {
        $expectedRows = $this->insertFixtures();

        foreach ($expectedRows as $expectedRow) {
            $this->assertEquals($expectedRow['val'], $this->select($expectedRow['id']));
        }
    }

    public function testInsertAndSelectMultiple(): void
    {
        $expectedRows = $this->insertFixtures();

        $rows = $this->selectRowsPositional($expectedRows[0]['id'], $expectedRows[1]['id']);
        $this->assertEquals($expectedRows, $rows);

        $rows = $this->selectRowsIndexed($expectedRows[0]['id'], $expectedRows[1]['id']);
        $this->assertEquals($expectedRows, $rows);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!Type::hasType(SimpleObjectType::SIMPLE_OBJECT)) {
            Type::addType(SimpleObjectType::SIMPLE_OBJECT, SimpleObjectType::class);
        }

        $table = new Table(self::TEST_TABLE);
        $table->addColumn(
            'id',
            'simple_object',
            [
                'unique' => true,
            ]
        );
        $table->addColumn('val', 'simple_object');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    private function insert(SimpleObject $id, SimpleObject $value): void
    {
        $result = $this->connection->insert(
            self::TEST_TABLE,
            [
                'id' => $id,
                'val' => $value,
            ],
            [
                Type::getType(SimpleObjectType::SIMPLE_OBJECT),
                Type::getType(SimpleObjectType::SIMPLE_OBJECT),
            ]
        );

        self::assertSame(1, $result);
    }

    private function select(SimpleObject $id): SimpleObject
    {
        $scalarValue = $this->connection->fetchOne(
            'SELECT val FROM '.self::TEST_TABLE.' WHERE id = ?',
            [$id],
            [Type::getType(SimpleObjectType::SIMPLE_OBJECT)]
        );

        return $this->connection->convertToPHPValue($scalarValue, SimpleObjectType::SIMPLE_OBJECT);
    }

    private function selectRowsPositional(SimpleObject $id1, SimpleObject $id2): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT id, val FROM '.self::TEST_TABLE.' WHERE id IN (?)',
            [[$id1, $id2]],
            [new ArrayOf(Type::getType(SimpleObjectType::SIMPLE_OBJECT))]
        );

        return $this->convertResults($results);
    }

    private function selectRowsIndexed(SimpleObject $id1, SimpleObject $id2): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT id, val FROM '.self::TEST_TABLE.' WHERE id IN (:ids)',
            ['ids' => [$id1, $id2]],
            ['ids' => new ArrayOf(Type::getType(SimpleObjectType::SIMPLE_OBJECT))]
        );

        return $this->convertResults($results);
    }

    private function convertResults(array $results): array
    {
        return array_map(
            function ($row) {
                return [
                    'id' => $this->connection->convertToPHPValue($row['id'], SimpleObjectType::SIMPLE_OBJECT),
                    'val' => $this->connection->convertToPHPValue($row['val'], SimpleObjectType::SIMPLE_OBJECT),
                ];
            },
            $results
        );
    }

    private function insertFixtures(): array
    {
        $id1 = new SimpleObject('id1');
        $id2 = new SimpleObject('id2');
        $value1 = new SimpleObject('value1');
        $value2 = new SimpleObject('value2');

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        return [
            ['id' => $id1, 'val' => $value1],
            ['id' => $id2, 'val' => $value2],
        ];
    }
}

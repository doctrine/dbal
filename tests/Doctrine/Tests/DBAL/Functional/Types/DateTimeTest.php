<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\ArrayOf;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;

class DateTimeTest extends DbalFunctionalTestCase
{
    private const TEST_TABLE = 'datetime_table';

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

        $table = new Table(self::TEST_TABLE);
        $table->addColumn(
            'id',
            Types::DATETIME_MUTABLE,
            [
                'unique' => true,
            ]
        );
        $table->addColumn('val', Types::DATETIME_MUTABLE);
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    private function insert(\DateTimeInterface $id, \DateTimeInterface $value): void
    {
        $result = $this->connection->insert(
            self::TEST_TABLE,
            [
                'id' => $id,
                'val' => $value,
            ],
            [
                Type::getType(Types::DATETIME_MUTABLE),
                Type::getType(Types::DATETIME_MUTABLE),
            ]
        );

        self::assertSame(1, $result);
    }

    private function select(\DateTimeInterface $id): \DateTimeInterface
    {
        $scalarValue = $this->connection->fetchOne(
            'SELECT val FROM '.self::TEST_TABLE.' WHERE id = ?',
            [$id],
            [Type::getType(Types::DATETIME_MUTABLE)]
        );

        return $this->connection->convertToPHPValue($scalarValue, Types::DATETIME_MUTABLE);
    }

    private function selectRowsPositional(\DateTimeInterface $id1, \DateTimeInterface $id2): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT id, val FROM '.self::TEST_TABLE.' WHERE id IN (?)',
            [[$id1, $id2]],
            [new ArrayOf(Type::getType(Types::DATETIME_MUTABLE))]
        );

        return $this->convertResults($results);
    }

    private function selectRowsIndexed(\DateTimeInterface $id1, \DateTimeInterface $id2): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT id, val FROM '.self::TEST_TABLE.' WHERE id IN (:ids)',
            ['ids' => [$id1, $id2]],
            ['ids' => new ArrayOf(Type::getType(Types::DATETIME_MUTABLE))]
        );

        return $this->convertResults($results);
    }

    private function convertResults(array $results): array
    {
        return array_map(
            function ($row) {
                return [
                    'id' => $this->connection->convertToPHPValue($row['id'], Types::DATETIME_MUTABLE),
                    'val' => $this->connection->convertToPHPValue($row['val'], Types::DATETIME_MUTABLE),
                ];
            },
            $results
        );
    }

    private function insertFixtures(): array
    {
        $id1 = new \DateTime('2021-06-05 00:00:00');
        $id2 = new \DateTime('2021-06-25 00:00:00');
        $value1 = new \DateTime('2021-06-05 12:00:00');
        $value2 = new \DateTime('2021-06-25 12:00:00');

        $this->insert($id1, $value1);
        $this->insert($id2, $value2);

        return [
            ['id' => $id1, 'val' => $value1],
            ['id' => $id2, 'val' => $value2],
        ];
    }
}

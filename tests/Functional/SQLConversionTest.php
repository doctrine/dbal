<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\Types\CaseConvertingStringType;
use Doctrine\DBAL\Types\Types;

/**
 * Covers the SQL-level conversion of DBAL mapping types ({@see Type::convertToDatabaseValueSQL()} and
 * {@see Type::convertToPHPValueSQL()}) in the SQL built by the connection and the query builder.
 */
class SQLConversionTest extends FunctionalTestCase
{
    private CaseConvertingStringType $type;

    protected function setUp(): void
    {
        $this->type = new CaseConvertingStringType();

        $table = Table::editor()
            ->setUnquotedName('sql_conversion')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('name')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setNotNull(false)
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

    public function testInsert(): void
    {
        $this->connection->insert('sql_conversion', ['id' => 1, 'name' => 'Foo'], ['name' => $this->type]);
        $this->connection->insert('sql_conversion', ['id' => 2, 'name' => 'Bar'], [1 => $this->type]);
        $this->connection->insert('sql_conversion', ['id' => 3, 'name' => null], ['name' => $this->type]);

        self::assertSame([
            [1, 'foo'],
            [2, 'bar'],
            [3, null],
        ], $this->fetchRows());
    }

    public function testUpdate(): void
    {
        $this->insertRows();

        $affected = $this->connection->update(
            'sql_conversion',
            ['name' => 'Baz'],
            ['name' => 'FOO'],
            ['name' => $this->type],
        );

        self::assertSame(1, (int) $affected);
        self::assertSame([
            [1, 'baz'],
            [2, 'bar'],
        ], $this->fetchRows());
    }

    public function testDelete(): void
    {
        $this->insertRows();

        $affected = $this->connection->delete('sql_conversion', ['name' => 'BAR'], ['name' => $this->type]);

        self::assertSame(1, (int) $affected);
        self::assertSame([[1, 'foo']], $this->fetchRows());
    }

    public function testQueryBuilderNamedParameter(): void
    {
        $this->insertRows();

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('sql_conversion')
            ->where($qb->expr()->eq('name', $qb->createNamedParameter('BAR', $this->type)));

        self::assertSame([2], $this->toIntegers($qb->fetchFirstColumn()));
    }

    public function testQueryBuilderPositionalParameter(): void
    {
        $this->insertRows();

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('sql_conversion')
            ->where($qb->expr()->eq('name', $qb->createPositionalParameter('FOO', $this->type)));

        self::assertSame([1], $this->toIntegers($qb->fetchFirstColumn()));
    }

    public function testHandWrittenSQL(): void
    {
        $this->insertRows();

        $sql = 'SELECT ' . $this->connection->convertToPHPValueSQL('name', $this->type)
            . ' FROM sql_conversion WHERE name = '
            . $this->connection->convertToDatabaseValueSQL('?', $this->type);

        self::assertSame('BAR', $this->connection->fetchOne($sql, ['Bar']));
    }

    private function insertRows(): void
    {
        $this->connection->insert('sql_conversion', ['id' => 1, 'name' => 'Foo'], ['name' => $this->type]);
        $this->connection->insert('sql_conversion', ['id' => 2, 'name' => 'Bar'], ['name' => $this->type]);
    }

    /** @return list<array{int, ?string}> */
    private function fetchRows(): array
    {
        $rows = [];

        $sql = 'SELECT id, name FROM sql_conversion ORDER BY id';

        foreach ($this->connection->fetchAllNumeric($sql) as [$id, $name]) {
            $rows[] = [(int) $id, $name];
        }

        return $rows;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<int>
     */
    private function toIntegers(array $values): array
    {
        $integers = [];

        foreach ($values as $value) {
            $integers[] = (int) $value;
        }

        return $integers;
    }
}

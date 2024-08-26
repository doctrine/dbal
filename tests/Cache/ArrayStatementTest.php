<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Cache;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function array_values;

class ArrayStatementTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $users = [
        [
            'username' => 'jwage',
            'active' => true,
        ],
        [
            'username' => 'romanb',
            'active' => false,
        ],
    ];

    public function testCloseCursor(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->rowCount());

        $statement->free();

        self::assertSame(0, $statement->rowCount());
    }

    public function testColumnCount(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->columnCount());
    }

    public function testColumnNames(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame('username', $statement->getColumnName(0));
        self::assertSame('active', $statement->getColumnName(1));
    }

    #[TestWith([2])]
    #[TestWith([-1])]
    public function testColumnNameWithInvalidIndex(int $index): void
    {
        $statement = $this->createTestArrayStatement();
        $this->expectException(InvalidColumnIndex::class);

        $statement->getColumnName($index);
    }

    public function testRowCount(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->rowCount());
    }

    public function testFetchAssociative(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame($this->users[0], $statement->fetchAssociative());
    }

    public function testFetchNumeric(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(array_values($this->users[0]), $statement->fetchNumeric());
    }

    public function testFetchOne(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame('jwage', $statement->fetchOne());
        self::assertSame('romanb', $statement->fetchOne());
    }

    public function testFetchAll(): void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame($this->users, $statement->fetchAllAssociative());
    }

    private function createTestArrayStatement(): ArrayResult
    {
        return new ArrayResult($this->users);
    }
}

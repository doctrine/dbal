<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Cache;

use Doctrine\DBAL\Cache\ArrayResult;
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

<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Cache;

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\FetchMode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function array_values;
use function iterator_to_array;

class ArrayStatementTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private $users = [
        [
            'username' => 'jwage',
            'active' => true,
        ],
        [
            'username' => 'romanb',
            'active' => false,
        ],
    ];

    public function testCloseCursor() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->rowCount());

        $statement->closeCursor();

        self::assertSame(0, $statement->rowCount());
    }

    public function testColumnCount() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->columnCount());
    }

    public function testRowCount() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(2, $statement->rowCount());
    }

    public function testSetFetchMode() : void
    {
        $statement = $this->createTestArrayStatement();

        $statement->setFetchMode(FetchMode::ASSOCIATIVE);

        self::assertSame($this->users[0], $statement->fetch());
    }

    public function testSetFetchModeThrowsInvalidArgumentException() : void
    {
        $statement = $this->createTestArrayStatement();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Caching layer does not support 2nd/3rd argument to setFetchMode().');

        $statement->setFetchMode(FetchMode::ASSOCIATIVE, 'arg1', 'arg2');
    }

    public function testGetIterator() : void
    {
        $statement = $this->createTestArrayStatement();
        $statement->setFetchMode(FetchMode::ASSOCIATIVE);

        self::assertSame($this->users, iterator_to_array($statement->getIterator()));
    }

    public function testFetchModeAssociative() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame($this->users[0], $statement->fetch(FetchMode::ASSOCIATIVE));
    }

    public function testFetchModeNumeric() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame(array_values($this->users[0]), $statement->fetch(FetchMode::NUMERIC));
    }

    public function testFetchModeMixed() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame([
            'username' => 'jwage',
            'active' => true,
            0 => 'jwage',
            1 => true,
        ], $statement->fetch(FetchMode::MIXED));
    }

    public function testFetchModeColumn() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame('jwage', $statement->fetch(FetchMode::COLUMN));
    }

    public function testFetchThrowsInvalidArgumentException() : void
    {
        $statement = $this->createTestArrayStatement();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fetch mode given for fetching result, 9999 given.');

        $statement->fetch(9999);
    }

    public function testFetchAll() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame($this->users, $statement->fetchAll(FetchMode::ASSOCIATIVE));
    }

    public function testFetchColumn() : void
    {
        $statement = $this->createTestArrayStatement();

        self::assertSame('jwage', $statement->fetchColumn(0));
        self::assertSame('romanb', $statement->fetchColumn(0));

        $statement = $this->createTestArrayStatement();

        self::assertTrue($statement->fetchColumn(1));
        self::assertFalse($statement->fetchColumn(1));
    }

    private function createTestArrayStatement() : ArrayStatement
    {
        return new ArrayStatement($this->users);
    }
}

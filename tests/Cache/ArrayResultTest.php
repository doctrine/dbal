<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Cache;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function assert;
use function file_get_contents;
use function serialize;
use function unserialize;

class ArrayResultTest extends TestCase
{
    private ArrayResult $result;

    protected function setUp(): void
    {
        parent::setUp();

        $this->result = new ArrayResult(['username', 'active'], [
            ['jwage', true],
            ['romanb', false],
        ]);
    }

    public function testFree(): void
    {
        self::assertSame(2, $this->result->rowCount());

        $this->result->free();

        self::assertSame(0, $this->result->rowCount());
    }

    public function testColumnCount(): void
    {
        self::assertSame(2, $this->result->columnCount());
    }

    public function testColumnNames(): void
    {
        self::assertSame('username', $this->result->getColumnName(0));
        self::assertSame('active', $this->result->getColumnName(1));
    }

    #[TestWith([2])]
    #[TestWith([-1])]
    public function testColumnNameWithInvalidIndex(int $index): void
    {
        $this->expectException(InvalidColumnIndex::class);

        $this->result->getColumnName($index);
    }

    public function testRowCount(): void
    {
        self::assertSame(2, $this->result->rowCount());
    }

    public function testFetchAssociative(): void
    {
        self::assertSame([
            'username' => 'jwage',
            'active' => true,
        ], $this->result->fetchAssociative());
    }

    public function testFetchNumeric(): void
    {
        self::assertSame(['jwage', true], $this->result->fetchNumeric());
    }

    public function testFetchOne(): void
    {
        self::assertSame('jwage', $this->result->fetchOne());
        self::assertSame('romanb', $this->result->fetchOne());
    }

    public function testFetchAllAssociative(): void
    {
        self::assertSame([
            [
                'username' => 'jwage',
                'active' => true,
            ],
            [
                'username' => 'romanb',
                'active' => false,
            ],
        ], $this->result->fetchAllAssociative());
    }

    public function testEmptyResult(): void
    {
        $result = new ArrayResult(['a'], []);
        self::assertSame('a', $result->getColumnName(0));
    }

    public function testSameColumnNames(): void
    {
        $result = new ArrayResult(['a', 'a'], [[1, 2]]);

        self::assertSame('a', $result->getColumnName(0));
        self::assertSame('a', $result->getColumnName(1));

        self::assertEquals([1, 2], $result->fetchNumeric());
    }

    public function testSerialize(): void
    {
        $result = unserialize(serialize($this->result));

        self::assertSame([
            [
                'username' => 'jwage',
                'active' => true,
            ],
            [
                'username' => 'romanb',
                'active' => false,
            ],
        ], $result->fetchAllAssociative());

        self::assertSame(2, $result->columnCount());
        self::assertSame('username', $result->getColumnName(0));
    }

    public function testRowPointerIsNotSerialized(): void
    {
        $this->result->fetchAssociative();
        $result = unserialize(serialize($this->result));

        self::assertSame([
            'username' => 'jwage',
            'active' => true,
        ], $result->fetchAssociative());
    }

    #[DataProvider('provideSerializedResultFiles')]
    public function testUnserialize(string $file): void
    {
        $serialized = file_get_contents($file);
        assert($serialized !== false);
        $result = unserialize($serialized);

        self::assertInstanceOf(ArrayResult::class, $result);
        self::assertSame([
            [
                'username' => 'jwage',
                'active' => true,
            ],
            [
                'username' => 'romanb',
                'active' => false,
            ],
        ], $result->fetchAllAssociative());

        self::assertSame(2, $result->columnCount());
        self::assertSame('username', $result->getColumnName(0));
    }

    /** @return iterable<string, array{string}> */
    public static function provideSerializedResultFiles(): iterable
    {
        yield '4.1 format' => [__DIR__ . '/Fixtures/array-result-4.1.txt'];
        yield '4.2 format' => [__DIR__ . '/Fixtures/array-result-4.2.txt'];
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\Exception\NoKeyValue;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function iterator_to_array;

class FetchTest extends FunctionalTestCase
{
    private string $query;

    public function setUp(): void
    {
        $this->query = TestUtil::generateResultSetQuery([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], $this->connection->getDatabasePlatform());
    }

    public function testFetchNumeric(): void
    {
        self::assertEquals(['foo', 1], $this->connection->fetchNumeric($this->query));
    }

    public function testFetchOne(): void
    {
        self::assertEquals('foo', $this->connection->fetchOne($this->query));
    }

    public function testFetchAssociative(): void
    {
        self::assertEquals([
            'a' => 'foo',
            'b' => 1,
        ], $this->connection->fetchAssociative($this->query));
    }

    public function testFetchAllNumeric(): void
    {
        self::assertEquals([
            ['foo', 1],
            ['bar', 2],
            ['baz', 3],
        ], $this->connection->fetchAllNumeric($this->query));
    }

    public function testFetchAllAssociative(): void
    {
        self::assertEquals([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], $this->connection->fetchAllAssociative($this->query));
    }

    public function testFetchAllKeyValue(): void
    {
        self::assertEquals([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ], $this->connection->fetchAllKeyValue($this->query));
    }

    /**
     * This test covers the requirement for the statement result to have at least two columns,
     * not exactly two as PDO requires.
     */
    public function testFetchAllKeyValueWithLimit(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLServerPlatform) {
            self::markTestSkipped('See https://github.com/doctrine/dbal/issues/2374');
        }

        $query = $platform->modifyLimitQuery($this->query, 1, 1);

        self::assertEquals(['bar' => 2], $this->connection->fetchAllKeyValue($query));
    }

    public function testFetchAllKeyValueOneColumn(): void
    {
        $sql = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        $this->expectException(NoKeyValue::class);
        $this->connection->fetchAllKeyValue($sql);
    }

    public function testFetchAllAssociativeIndexed(): void
    {
        self::assertEquals([
            'foo' => ['b' => 1],
            'bar' => ['b' => 2],
            'baz' => ['b' => 3],
        ], $this->connection->fetchAllAssociativeIndexed($this->query));
    }

    public function testFetchFirstColumn(): void
    {
        self::assertEquals([
            'foo',
            'bar',
            'baz',
        ], $this->connection->fetchFirstColumn($this->query));
    }

    public function testIterateNumeric(): void
    {
        self::assertEquals([
            ['foo', 1],
            ['bar', 2],
            ['baz', 3],
        ], iterator_to_array($this->connection->iterateNumeric($this->query)));
    }

    public function testIterateAssociative(): void
    {
        self::assertEquals([
            [
                'a' => 'foo',
                'b' => 1,
            ],
            [
                'a' => 'bar',
                'b' => 2,
            ],
            [
                'a' => 'baz',
                'b' => 3,
            ],
        ], iterator_to_array($this->connection->iterateAssociative($this->query)));
    }

    public function testIterateKeyValue(): void
    {
        self::assertEquals([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ], iterator_to_array($this->connection->iterateKeyValue($this->query)));
    }

    public function testIterateKeyValueOneColumn(): void
    {
        $sql = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL();

        $this->expectException(NoKeyValue::class);
        iterator_to_array($this->connection->iterateKeyValue($sql));
    }

    public function testIterateAssociativeIndexed(): void
    {
        self::assertEquals([
            'foo' => ['b' => 1],
            'bar' => ['b' => 2],
            'baz' => ['b' => 3],
        ], iterator_to_array($this->connection->iterateAssociativeIndexed($this->query)));
    }

    public function testIterateColumn(): void
    {
        self::assertEquals([
            'foo',
            'bar',
            'baz',
        ], iterator_to_array($this->connection->iterateColumn($this->query)));
    }
}

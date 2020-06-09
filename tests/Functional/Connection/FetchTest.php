<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function iterator_to_array;

class FetchTest extends FunctionalTestCase
{
    /** @var string */
    private $query;

    public function setUp(): void
    {
        parent::setUp();

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

    public function testIterateColumn(): void
    {
        self::assertEquals([
            'foo',
            'bar',
            'baz',
        ], iterator_to_array($this->connection->iterateColumn($this->query)));
    }
}

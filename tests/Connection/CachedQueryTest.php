<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CachedQueryTest extends TestCase
{
    public function testCachedQuery(): void
    {
        $cache = new ArrayAdapter();

        $connection = $this->createConnection(1, ['foo'], [['bar']]);
        $qcp        = new QueryCacheProfile(0, __FUNCTION__, $cache);

        self::assertSame([['foo' => 'bar']], $connection->executeCacheQuery('SELECT 1', [], [], $qcp)
            ->fetchAllAssociative());
        self::assertSame([['foo' => 'bar']], $connection->executeCacheQuery('SELECT 1', [], [], $qcp)
            ->fetchAllAssociative());

        self::assertCount(1, $cache->getItem(__FUNCTION__)->get());
    }

    public function testCachedQueryWithChangedImplementationIsExecutedTwice(): void
    {
        $connection = $this->createConnection(2, ['baz'], [['qux']]);

        self::assertSame([['baz' => 'qux']], $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, new ArrayAdapter()),
        )->fetchAllAssociative());

        self::assertSame([['baz' => 'qux']], $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, new ArrayAdapter()),
        )->fetchAllAssociative());
    }

    public function testOldCacheFormat(): void
    {
        $connection = $this->createConnection(1, ['foo'], [['bar']]);
        $cache      = new ArrayAdapter();
        $qcp        = new QueryCacheProfile(0, __FUNCTION__, $cache);

        [$cacheKey, $realKey] = $qcp->generateCacheKeys('SELECT 1', [], [], []);
        $cache->save(
            $cache->getItem($cacheKey)->set([$realKey => [['foo' => 'bar']]]),
        );

        self::assertSame([['foo' => 'bar']], $connection->executeCacheQuery('SELECT 1', [], [], $qcp)
            ->fetchAllAssociative());
        self::assertSame([['foo' => 'bar']], $connection->executeCacheQuery('SELECT 1', [], [], $qcp)
            ->fetchAllAssociative());

        self::assertCount(1, $cache->getItem(__FUNCTION__)->get());
    }

    /**
     * @param list<string>      $columnNames
     * @param list<list<mixed>> $rows
     */
    private function createConnection(int $expectedQueryCount, array $columnNames, array $rows): Connection
    {
        $connection = $this->createMock(Driver\Connection::class);
        $connection->expects(self::exactly($expectedQueryCount))
            ->method('query')
            ->willReturnCallback(static fn (): ArrayResult => new ArrayResult($columnNames, $rows));

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')
            ->willReturn($connection);

        return new Connection([], $driver);
    }
}

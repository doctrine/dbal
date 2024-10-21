<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CachedQueryTest extends TestCase
{
    #[DataProvider('providePsrCacheImplementations')]
    public function testCachedQuery(callable $psrCacheProvider): void
    {
        $cache = $psrCacheProvider();

        $connection = $this->createConnection(1, ['foo'], [['bar']]);
        $qcp        = new QueryCacheProfile(0, __FUNCTION__, $cache);

        $firstResult = $connection->executeCacheQuery('SELECT 1', [], [], $qcp);
        self::assertSame([['foo' => 'bar']], $firstResult
            ->fetchAllAssociative());
        $firstResult->free();
        $secondResult = $connection->executeCacheQuery('SELECT 1', [], [], $qcp);
        self::assertSame([['foo' => 'bar']], $secondResult
            ->fetchAllAssociative());
        $secondResult->free();
        self::assertSame([['foo' => 'bar']], $connection->executeCacheQuery('SELECT 1', [], [], $qcp)
            ->fetchAllAssociative());

        self::assertCount(1, $cache->getItem(__FUNCTION__)->get());
    }

    #[DataProvider('providePsrCacheImplementations')]
    public function testCachedQueryWithChangedImplementationIsExecutedTwice(callable $psrCacheProvider): void
    {
        $connection = $this->createConnection(2, ['baz'], [['qux']]);

        self::assertSame([['baz' => 'qux']], $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, $psrCacheProvider()),
        )->fetchAllAssociative());

        self::assertSame([['baz' => 'qux']], $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, $psrCacheProvider()),
        )->fetchAllAssociative());
    }

    #[DataProvider('providePsrCacheImplementations')]
    public function testOldCacheFormat(callable $psrCacheProvider): void
    {
        $connection = $this->createConnection(1, ['foo'], [['bar']]);
        $cache      = $psrCacheProvider();
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

    /** @return array<non-empty-string, list<callable():CacheItemPoolInterface>> */
    public static function providePsrCacheImplementations(): array
    {
        return [
            'serialized' => [static fn () => new ArrayAdapter(0, true)],
            'by-reference' => [static fn () => new ArrayAdapter(0, false)],
        ];
    }
}

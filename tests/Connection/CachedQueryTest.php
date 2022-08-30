<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function class_exists;

class CachedQueryTest extends TestCase
{
    public function testCachedQuery(): void
    {
        $cache = new ArrayAdapter();
        $this->assertCachedQueryIsExecutedOnceAndYieldsTheSameResult($cache, __FUNCTION__);
        self::assertCount(1, $cache->getItem(__FUNCTION__)->get());
    }

    public function testCachedQueryLegacy(): void
    {
        if (! class_exists(ArrayCache::class)) {
            self::markTestSkipped('This test requires the legacy ArrayCache class.');
        }

        $cache = new ArrayCache();
        $this->assertCachedQueryIsExecutedOnceAndYieldsTheSameResult($cache, __FUNCTION__);

        self::assertCount(1, $cache->fetch(__FUNCTION__));
    }

    public function testCachedQueryLegacyWrapped(): void
    {
        $cache  = new ArrayAdapter();
        $legacy = DoctrineProvider::wrap($cache);
        $this->assertCachedQueryIsExecutedOnceAndYieldsTheSameResult($legacy, __FUNCTION__);

        self::assertCount(1, $cache->getItem(__FUNCTION__)->get());
    }

    /** @param CacheItemPoolInterface|Cache $cache */
    private function assertCachedQueryIsExecutedOnceAndYieldsTheSameResult(object $cache, string $cacheKey): void
    {
        $data = [['foo' => 'bar']];

        $connection = $this->createConnection(1, $data);
        $qcp        = new QueryCacheProfile(0, $cacheKey, $cache);

        self::assertSame($data, $connection->executeCacheQuery('SELECT 1', [], [], $qcp)->fetchAllAssociative());
        self::assertSame($data, $connection->executeCacheQuery('SELECT 1', [], [], $qcp)->fetchAllAssociative());
    }

    public function testCachedQueryWithChangedImplementationIsExecutedTwice(): void
    {
        $data = [['baz' => 'qux']];

        $connection = $this->createConnection(2, $data);

        self::assertSame($data, $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, new ArrayAdapter()),
        )->fetchAllAssociative());

        self::assertSame($data, $connection->executeCacheQuery(
            'SELECT 1',
            [],
            [],
            new QueryCacheProfile(0, __FUNCTION__, new ArrayAdapter()),
        )->fetchAllAssociative());
    }

    /** @param list<array<string, mixed>> $data */
    private function createConnection(int $expectedQueryCount, array $data): Connection
    {
        $connection = $this->createMock(Driver\Connection::class);
        $connection->expects(self::exactly($expectedQueryCount))
            ->method('query')
            ->willReturnCallback(static function () use ($data): ArrayResult {
                return new ArrayResult($data);
            });

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')
            ->willReturn($connection);

        return new Connection([], $driver);
    }
}

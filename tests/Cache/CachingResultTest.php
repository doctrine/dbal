<?php

namespace Doctrine\DBAL\Tests\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Cache\CachingResult;
use Doctrine\DBAL\Driver\Result;
use PHPUnit\Framework\TestCase;

class CachingResultTest extends TestCase
{
    /** @var string */
    private $cacheKey = 'cacheKey';

    /** @var string */
    private $realKey = 'realKey';

    /** @var int */
    private $lifetime = 3600;

    /** @var array<string, mixed> */
    private $resultData = ['id' => 123, 'field' => 'value'];

    /** @var array<string, mixed> */
    private $cachedData;

    /** @var Cache */
    private $cache;

    /** @var Result */
    private $result;

    protected function setUp(): void
    {
        $this->result = $this->createMock(Result::class);
        $this->result->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->will($this->onConsecutiveCalls($this->resultData, false));

        $this->cache = $this->createMock(Cache::class);
        $this->cache->expects(self::exactly(1))
            ->method('save')
            ->willReturnCallback(function (string $id, $data, int $ttl): void {
                $this->assertEquals($this->cacheKey, $id, 'The cache key should match the given one');
                $this->assertEquals($this->lifetime, $ttl, 'The cache key ttl should match the given one');
                $this->cachedData = $data;
            });
    }

    public function testShouldSaveResultToCache(): void
    {
        $cachingResult = new CachingResult(
            $this->result,
            $this->cache,
            $this->cacheKey,
            $this->realKey,
            $this->lifetime,
            ['otherRealKey' => 'resultValue']
        );

        do {
            $row = $cachingResult->fetchAssociative();
        } while ($row !== false);

        $this->assertContains(
            $this->resultData,
            $this->cachedData[$this->realKey],
            'CachingResult should cache data from the given result'
        );

        $this->assertEquals(
            'resultValue',
            $this->cachedData['otherRealKey'],
            'CachingResult should not change other keys from cache'
        );
    }
}

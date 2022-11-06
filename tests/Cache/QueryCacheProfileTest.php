<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Cache;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

use function parse_str;

class QueryCacheProfileTest extends TestCase
{
    private const LIFETIME  = 3600;
    private const CACHE_KEY = 'user_specified_cache_key';

    private QueryCacheProfile $queryCacheProfile;
    private string $query = 'SELECT * FROM foo WHERE bar = ?';

    /** @var list<mixed> */
    private array $params = [666];

    /** @var list<ParameterType::INTEGER> */
    private array $types = [ParameterType::INTEGER];

    /** @var array<string, mixed> */
    private array $connectionParams = [
        'dbname'   => 'database_name',
        'user'     => 'database_user',
        'password' => 'database_password',
        'host'     => 'database_host',
        'driver'   => 'database_driver',
    ];

    protected function setUp(): void
    {
        $this->queryCacheProfile = new QueryCacheProfile(self::LIFETIME, self::CACHE_KEY);
    }

    public function testShouldUseTheGivenCacheKeyIfPresent(): void
    {
        [$cacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        self::assertEquals(self::CACHE_KEY, $cacheKey, 'The returned cache key should match the given one');
    }

    public function testShouldGenerateAnAutomaticKeyIfNoKeyHasBeenGiven(): void
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$cacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        self::assertNotEquals(
            self::CACHE_KEY,
            $cacheKey,
            'The returned cache key should be generated automatically',
        );

        self::assertNotEmpty($cacheKey, 'The generated cache key should not be empty');
    }

    public function testShouldGenerateDifferentKeysForSameQueryAndParamsAndDifferentConnections(): void
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$firstCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        $this->connectionParams['host'] = 'a_different_host';

        [$secondCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        self::assertNotEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be different');
    }

    public function testConnectionParamsShouldBeHashed(): void
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [, $queryString] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        $params = [];
        parse_str($queryString, $params);

        self::assertArrayHasKey('connectionParams', $params);

        foreach ($this->connectionParams as $param) {
            self::assertIsString($params['connectionParams']);
            self::assertStringNotContainsString($param, $params['connectionParams']);
        }
    }

    public function testShouldGenerateSameKeysIfNoneOfTheParamsChanges(): void
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$firstCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        [$secondCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams,
        );

        self::assertEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be the same');
    }

    public function testShouldGenerateDifferentPasswordInTheParams(): void
    {
        [, $firstRealCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            [
                'user'     => 'database_user',
                'password' => 'first-password',
            ],
        );

        [, $secondRealCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            [
                'user'     => 'database_user',
                'password' => 'second-password',
            ],
        );

        self::assertEquals(
            $firstRealCacheKey,
            $secondRealCacheKey,
            'Cache keys for different password should be the same',
        );
    }
}

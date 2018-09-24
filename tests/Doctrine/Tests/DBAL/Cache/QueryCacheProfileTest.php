<?php

namespace Doctrine\Tests\DBAL\Cache;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\ParameterType;
use Doctrine\Tests\DbalTestCase;
use function parse_str;

class QueryCacheProfileTest extends DbalTestCase
{
    private const LIFETIME  = 3600;
    private const CACHE_KEY = 'user_specified_cache_key';

    /** @var QueryCacheProfile */
    private $queryCacheProfile;

    /** @var string */
    private $query = 'SELECT * FROM foo WHERE bar = ?';

    /** @var int[] */
    private $params = [666];

    /** @var string[] */
    private $types = [ParameterType::INTEGER];

    /** @var string[] */
    private $connectionParams = [
        'dbname'   => 'database_name',
        'user'     => 'database_user',
        'password' => 'database_password',
        'host'     => 'database_host',
        'driver'   => 'database_driver',
    ];

    protected function setUp()
    {
        $this->queryCacheProfile = new QueryCacheProfile(self::LIFETIME, self::CACHE_KEY);
    }

    public function testShouldUseTheGivenCacheKeyIfPresent()
    {
        [$cacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        self::assertEquals(self::CACHE_KEY, $cacheKey, 'The returned cache key should match the given one');
    }

    public function testShouldGenerateAnAutomaticKeyIfNoKeyHasBeenGiven()
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$cacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        self::assertNotEquals(
            self::CACHE_KEY,
            $cacheKey,
            'The returned cache key should be generated automatically'
        );

        self::assertNotEmpty($cacheKey, 'The generated cache key should not be empty');
    }

    public function testShouldGenerateDifferentKeysForSameQueryAndParamsAndDifferentConnections()
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$firstCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        $this->connectionParams['host'] = 'a_different_host';

        [$secondCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        self::assertNotEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be different');
    }

    public function testConnectionParamsShouldBeHashed()
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$cacheKey, $queryString] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        $params = [];
        parse_str($queryString, $params);

        self::assertArrayHasKey('connectionParams', $params);

        foreach ($this->connectionParams as $param) {
            self::assertNotContains($param, $params['connectionParams']);
        }
    }

    public function testShouldGenerateSameKeysIfNoneOfTheParamsChanges()
    {
        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        [$firstCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        [$secondCacheKey] = $this->queryCacheProfile->generateCacheKeys(
            $this->query,
            $this->params,
            $this->types,
            $this->connectionParams
        );

        self::assertEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be the same');
    }
}

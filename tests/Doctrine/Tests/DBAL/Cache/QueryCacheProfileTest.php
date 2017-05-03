<?php

namespace Doctrine\Tests\DBAL\Cache;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\Tests\DbalTestCase;
use PDO;

class QueryCacheProfileTest extends DbalTestCase
{
    const LIFETIME = 3600;
    const CACHE_KEY = 'user_specified_cache_key';

    /** @var QueryCacheProfile */
    private $queryCacheProfile;

    protected function setUp()
    {
        $this->queryCacheProfile = new QueryCacheProfile(self::LIFETIME, self::CACHE_KEY);
    }

    public function testShouldUseTheGivenCacheKeyIfPresent()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [PDO::PARAM_INT];

        $connectionParams = array(
            'dbname'   => 'database_name',
            'user'     => 'database_user',
            'password' => 'database_password',
            'host'     => 'database_host',
            'driver'   => 'database_driver'
        );

        list($cacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertEquals(self::CACHE_KEY, $cacheKey, 'The returned cache key should match the given one');
    }

    public function testShouldGenerateAnAutomaticKeyIfNoKeyHasBeenGiven()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [PDO::PARAM_INT];

        $connectionParams = array(
            'dbname'   => 'database_name',
            'user'     => 'database_user',
            'password' => 'database_password',
            'host'     => 'database_host',
            'driver'   => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        list($cacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertNotEquals(
            self::CACHE_KEY,
            $cacheKey,
            'The returned cache key should be generated automatically'
        );

        $this->assertNotEmpty($cacheKey, 'The generated cache key should not be empty');
    }

    public function testShouldGenerateDifferentKeysForSameQueryAndParamsAndDifferentConnections()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [PDO::PARAM_INT];

        $connectionParams = array(
            'dbname'   => 'database_name',
            'user'     => 'database_user',
            'password' => 'database_password',
            'host'     => 'database_host',
            'driver'   => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        list($firstCacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $connectionParams['host'] = 'a_different_host';

        list($secondCacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertNotEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be different');
    }

    public function testShouldGenerateSameKeysIfNoneOfTheParamsChanges()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = [666];
        $types  = [PDO::PARAM_INT];

        $connectionParams = array(
            'dbname'   => 'database_name',
            'user'     => 'database_user',
            'password' => 'database_password',
            'host'     => 'database_host',
            'driver'   => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        list($firstCacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        list($secondCacheKey) = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be the same');
    }
}

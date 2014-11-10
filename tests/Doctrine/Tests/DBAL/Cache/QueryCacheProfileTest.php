<?php

namespace Doctrine\Tests\DBAL\Cache;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\Tests\DbalTestCase;

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
        $params = array(666);
        $types  = array(\PDO::PARAM_INT);

        $connectionParams = array(
            'dbname' => 'database_name',
            'user' => 'database_user',
            'password' => 'database_password',
            'host' => 'database_host',
            'driver' => 'database_driver'
        );

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertEquals(self::CACHE_KEY, $generatedKeys[0], 'The returned cache key should match the given one');
    }

    public function testShouldGenerateAnAutomaticKeyIfNoKeyHasBeenGiven()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = array(666);
        $types  = array(\PDO::PARAM_INT);

        $connectionParams = array(
            'dbname' => 'database_name',
            'user' => 'database_user',
            'password' => 'database_password',
            'host' => 'database_host',
            'driver' => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $this->assertNotEquals(
            self::CACHE_KEY,
            $generatedKeys[0],
            'The returned cache key should be generated automatically'
        );

        $this->assertNotEmpty($generatedKeys[0], 'The generated cache key should not be empty');
    }

    public function testShouldGenerateDifferentKeysForSameQueryAndParamsAndDifferentConnections()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = array(666);
        $types  = array(\PDO::PARAM_INT);

        $connectionParams = array(
            'dbname' => 'database_name',
            'user' => 'database_user',
            'password' => 'database_password',
            'host' => 'database_host',
            'driver' => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $firstCacheKey = $generatedKeys[0];

        $connectionParams['host'] = 'a_different_host';

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $secondCacheKey = $generatedKeys[0];

        $this->assertNotEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be different');
    }

    public function testShouldGenerateSameKeysIfNoneOfTheParamsChanges()
    {
        $query  = 'SELECT * FROM foo WHERE bar = ?';
        $params = array(666);
        $types  = array(\PDO::PARAM_INT);

        $connectionParams = array(
            'dbname' => 'database_name',
            'user' => 'database_user',
            'password' => 'database_password',
            'host' => 'database_host',
            'driver' => 'database_driver'
        );

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $firstCacheKey = $generatedKeys[0];

        $generatedKeys = $this->queryCacheProfile->generateCacheKeys(
            $query,
            $params,
            $types,
            $connectionParams
        );

        $secondCacheKey = $generatedKeys[0];

        $this->assertEquals($firstCacheKey, $secondCacheKey, 'Cache keys should be the same');
    }
}

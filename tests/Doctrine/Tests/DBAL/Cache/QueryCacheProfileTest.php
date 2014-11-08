<?php

namespace Doctrine\Tests\DBAL\Cache;

require_once __DIR__ . '/../../TestInit.php';

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

    /**
     * @test
     */
    public function it_should_use_the_given_cache_key_if_present()
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

        $expectedRealCacheKey = 'query=SELECT * FROM foo WHERE bar = ?&params=a:1:{i:0;i:666;}&types=a:1:{i:0;'
            . 'i:1;}a:5:{s:6:"dbname";s:13:"database_name";s:4:"user";s:13:"database_user";s:8:"password";s:17:"'
            . 'database_password";s:4:"host";s:13:"database_host";s:6:"driver";s:15:"database_driver";}';

        $cacheKeysResult = $this->queryCacheProfile->generateCacheKeys($query, $params, $types, $connectionParams);

        $this->assertEquals(self::CACHE_KEY, $cacheKeysResult[0], 'The returned cached key should match the given one');
        $this->assertEquals($expectedRealCacheKey, $cacheKeysResult[1]);
    }

    /**
     * @test
     */
    public function it_should_generate_an_automatic_key_if_no_key_has_been_specified()
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

        $expectedRealCacheKey = 'query=SELECT * FROM foo WHERE bar = ?&params=a:1:{i:0;i:666;}&types=a:1:{i:0;'
            . 'i:1;}a:5:{s:6:"dbname";s:13:"database_name";s:4:"user";s:13:"database_user";s:8:"password";s:17:"'
            . 'database_password";s:4:"host";s:13:"database_host";s:6:"driver";s:15:"database_driver";}';

        $this->queryCacheProfile = $this->queryCacheProfile->setCacheKey(null);

        $cacheKeysResult = $this->queryCacheProfile->generateCacheKeys($query, $params, $types, $connectionParams);

        $this->assertEquals('8f74136f51719d57f4da9b6d2ba955b42189bb81', $cacheKeysResult[0]);
        $this->assertEquals($expectedRealCacheKey, $cacheKeysResult[1]);
    }
}

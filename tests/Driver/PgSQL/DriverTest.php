<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PgSQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PgSQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function in_array;

class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (isset($GLOBALS['db_driver']) && $GLOBALS['db_driver'] === 'pgsql') {
            return;
        }

        self::markTestSkipped('Test enabled only when using pgsql specific phpunit.xml');
    }

    /**
     * Ensure we can handle URI notation for IPv6 addresses
     */
    public function testConnectionIPv6(): void
    {
        if (! in_array($GLOBALS['db_host'], ['localhost', '127.0.0.1', '[::1]'], true)) {
            // We cannot assume that every contributor runs the same setup as our CI
            self::markTestSkipped('This test only works if there is a Postgres server listening on localhost.');
        }

        self::expectNotToPerformAssertions();

        $params         = TestUtil::getConnectionParams();
        $params['host'] = '[::1]';

        $this->driver->connect($params);
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}

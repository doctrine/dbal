<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\PgSQL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PgSQL\Driver;
use Doctrine\DBAL\Tests\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use ReflectionMethod;

use function in_array;

class DriverTest extends AbstractDriverTestCase
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

    public function testDriverOptionsArePassedThroughToConnectionString(): void
    {
        $params = [
            'dbname' => 'db',
            'driverOptions' => [
                'connect_timeout' => 5,
                'keepalives_idle' => 30,
                'options' => '-c search_path=foo',
            ],
        ];

        self::assertSame(
            "dbname='db' connect_timeout='5' keepalives_idle='30' options='-c search_path=foo'",
            self::constructConnectionString($params),
        );
    }

    public function testConnectionParamsTakePrecedenceOverDriverOptions(): void
    {
        $params = [
            'dbname' => 'db',
            'driverOptions' => ['dbname' => 'other'],
        ];

        self::assertSame("dbname='db'", self::constructConnectionString($params));
    }

    public function testIpv6HostFromDriverOptionsIsConvertedToHostaddr(): void
    {
        $params = [
            'dbname' => 'db',
            'driverOptions' => ['host' => '[::1]'],
        ];

        self::assertSame("hostaddr='::1' dbname='db'", self::constructConnectionString($params));
    }

    public function testApplicationNameFromParamsTakesPrecedenceOverDriverOptions(): void
    {
        $params = [
            'dbname' => 'db',
            'application_name' => 'app',
            'driverOptions' => ['application_name' => 'other'],
        ];

        self::assertSame("dbname='db' application_name='app'", self::constructConnectionString($params));
    }

    public function testDbnameFromDriverOptionsIsUsedWhenNotInParams(): void
    {
        $params = ['driverOptions' => ['dbname' => 'other']];

        self::assertSame("dbname='other'", self::constructConnectionString($params));
    }

    /** @param array<string, mixed> $params */
    private static function constructConnectionString(array $params): string
    {
        $method = new ReflectionMethod(Driver::class, 'constructConnectionString');

        return $method->invoke(null, $params);
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}

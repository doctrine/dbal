<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO;

use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function get_class;
use function sprintf;

/**
 * @requires extension pdo
 */
class ConnectionTest extends FunctionalTestCase
{
    /**
     * The PDO driver connection under test.
     */
    protected Connection $driverConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $driverConnection = $this->connection->getWrappedConnection();

        if (! $driverConnection instanceof Connection) {
            self::markTestSkipped('PDO connection only test.');
        }

        $this->driverConnection = $driverConnection;
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();

        parent::tearDown();
    }

    public function testThrowsWrappedExceptionOnConstruct(): void
    {
        $this->expectException(Exception::class);

        new Connection('foo');
    }

    public function testThrowsWrappedExceptionOnExec(): void
    {
        $this->expectException(Exception::class);

        $this->driverConnection->exec('foo');
    }

    public function testThrowsWrappedExceptionOnPrepare(): void
    {
        $driver = $this->connection->getDriver();

        if ($driver instanceof PDO\SQLSrv\Driver) {
            self::markTestSkipped('pdo_sqlsrv does not allow setting PDO::ATTR_EMULATE_PREPARES at connection level.');
        }

        // Some PDO adapters do not check the query server-side
        // even though emulated prepared statements are disabled,
        // so an exception is thrown only eventually.
        if (
            $driver instanceof PDO\OCI\Driver
            || $driver instanceof PDO\PgSQL\Driver
        ) {
            self::markTestSkipped(sprintf(
                'The underlying implementation of the %s driver does not check the query to be prepared server-side.',
                get_class($driver)
            ));
        }

        // Emulated prepared statements have to be disabled for this test
        // so that PDO actually communicates with the database server to check the query.
        $this->driverConnection
            ->getWrappedConnection()
            ->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        $this->expectException(Exception::class);

        $this->driverConnection->prepare('foo');
    }

    public function testThrowsWrappedExceptionOnQuery(): void
    {
        $this->expectException(Exception::class);

        $this->driverConnection->query('foo');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Driver\PDO;

use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\OCI\Driver as PDOOCIDriver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PDOPgSQLDriver;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver as PDOSQLSrvDriver;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDO;

use function get_class;
use function sprintf;

/**
 * @requires extension pdo
 */
class ConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The PDO driver connection under test.
     *
     * @var Connection
     */
    protected $driverConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverConnection = $this->connection->getWrappedConnection();

        if ($this->driverConnection instanceof Connection) {
            return;
        }

        $this->markTestSkipped('PDO connection only test.');
    }

    protected function tearDown(): void
    {
        $this->resetSharedConn();

        parent::tearDown();
    }

    public function testDoesNotRequireQueryForServerVersion(): void
    {
        self::assertFalse($this->driverConnection->requiresQueryForServerVersion());
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

        if ($driver instanceof PDOSQLSrvDriver) {
            $this->markTestSkipped('pdo_sqlsrv does not allow setting PDO::ATTR_EMULATE_PREPARES at connection level.');
        }

        // Some PDO adapters do not check the query server-side
        // even though emulated prepared statements are disabled,
        // so an exception is thrown only eventually.
        if (
            $driver instanceof PDOOCIDriver
            || $driver instanceof PDOPgSQLDriver
        ) {
            self::markTestSkipped(sprintf(
                'The underlying implementation of the %s driver does not check the query to be prepared server-side.',
                get_class($driver)
            ));
        }

        // Emulated prepared statements have to be disabled for this test
        // so that PDO actually communicates with the database server to check the query.
        $this->driverConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $this->expectException(Exception::class);

        $this->driverConnection->prepare('foo');
    }

    public function testThrowsWrappedExceptionOnQuery(): void
    {
        $this->expectException(Exception::class);

        $this->driverConnection->query('foo');
    }

    /**
     * This test ensures backward compatibility with DBAL 2.x and should be removed in 3.0.
     */
    public function testQuoteInteger(): void
    {
        self::assertSame("'1'", $this->connection->getWrappedConnection()->quote(1));
    }
}

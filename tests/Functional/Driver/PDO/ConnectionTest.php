<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO;

use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\Driver\PDOPgSql\Driver as PDOPgSQLDriver;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as PDOSQLSRVDriver;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PDO;

use function get_class;
use function sprintf;

/**
 * @requires extension pdo
 */
class ConnectionTest extends FunctionalTestCase
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

        self::markTestSkipped('PDO connection only test.');
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

    /**
     * @group DBAL-1022
     */
    public function testThrowsWrappedExceptionOnExec(): void
    {
        $this->expectException(Exception::class);

        $this->driverConnection->exec('foo');
    }

    public function testThrowsWrappedExceptionOnPrepare(): void
    {
        $driver = $this->connection->getDriver();

        if ($driver instanceof PDOSQLSRVDriver) {
            self::markTestSkipped('pdo_sqlsrv does not allow setting PDO::ATTR_EMULATE_PREPARES at connection level.');
        }

        // Some PDO adapters do not check the query server-side
        // even though emulated prepared statements are disabled,
        // so an exception is thrown only eventually.
        if (
            $driver instanceof PDOOracleDriver
            || $driver instanceof PDOPgSQLDriver
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
            ->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $this->expectException(Exception::class);

        $this->driverConnection->prepare('foo');
    }

    public function testThrowsWrappedExceptionOnQuery(): void
    {
        $this->expectException(Exception::class);

        $this->driverConnection->query('foo');
    }
}

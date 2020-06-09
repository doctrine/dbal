<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOException;
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
class PDOConnectionTest extends FunctionalTestCase
{
    /**
     * The PDO driver connection under test.
     *
     * @var PDOConnection
     */
    protected $driverConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $wrappedConnection = $this->connection->getWrappedConnection();

        if (! $wrappedConnection instanceof PDOConnection) {
            self::markTestSkipped('PDO connection only test.');
        }

        $this->driverConnection = $wrappedConnection;
    }

    protected function tearDown(): void
    {
        $this->resetSharedConn();

        parent::tearDown();
    }

    public function testThrowsWrappedExceptionOnConstruct(): void
    {
        $this->expectException(PDOException::class);

        new PDOConnection('foo');
    }

    /**
     * @group DBAL-1022
     */
    public function testThrowsWrappedExceptionOnExec(): void
    {
        $this->expectException(PDOException::class);

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

        $this->expectException(PDOException::class);

        $this->driverConnection->prepare('foo');
    }

    public function testThrowsWrappedExceptionOnQuery(): void
    {
        $this->expectException(PDOException::class);

        $this->driverConnection->query('foo');
    }
}

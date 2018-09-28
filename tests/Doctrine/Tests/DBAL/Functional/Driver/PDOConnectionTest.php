<?php

namespace Doctrine\Tests\DBAL\Functional\Driver;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\Tests\DbalFunctionalTestCase;
use PDO;
use function extension_loaded;
use function sprintf;

class PDOConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The PDO driver connection under test.
     *
     * @var PDOConnection
     */
    protected $driverConnection;

    protected function setUp()
    {
        if (! extension_loaded('PDO')) {
            $this->markTestSkipped('PDO is not installed.');
        }

        parent::setUp();

        $this->driverConnection = $this->connection->getWrappedConnection();

        if ($this->driverConnection instanceof PDOConnection) {
            return;
        }

        $this->markTestSkipped('PDO connection only test.');
    }

    protected function tearDown()
    {
        $this->resetSharedConn();

        parent::tearDown();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        self::assertFalse($this->driverConnection->requiresQueryForServerVersion());
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnConstruct()
    {
        new PDOConnection('foo');
    }

    /**
     * @group DBAL-1022
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnExec()
    {
        $this->driverConnection->exec('foo');
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnPrepare()
    {
        if ($this->connection->getDriver()->getName() === 'pdo_sqlsrv') {
            $this->markTestSkipped('pdo_sqlsrv does not allow setting PDO::ATTR_EMULATE_PREPARES at connection level.');
        }

        // Emulated prepared statements have to be disabled for this test
        // so that PDO actually communicates with the database server to check the query.
        $this->driverConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $this->driverConnection->prepare('foo');

        // Some PDO adapters like PostgreSQL do not check the query server-side
        // even though emulated prepared statements are disabled,
        // so an exception is thrown only eventually.
        // Skip the test otherwise.
        $this->markTestSkipped(
            sprintf(
                'The PDO adapter %s does not check the query to be prepared server-side, ' .
                'so no assertions can be made.',
                $this->connection->getDriver()->getName()
            )
        );
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\PDOException
     */
    public function testThrowsWrappedExceptionOnQuery()
    {
        $this->driverConnection->query('foo');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;

use const MYSQLI_OPT_CONNECT_TIMEOUT;

class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('mysqli')) {
            self::markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        self::markTestSkipped('MySQLi only test.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testSupportedDriverOptions(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getConnection([MYSQLI_OPT_CONNECT_TIMEOUT => 1]);
    }

    public function testUnsupportedDriverOption(): void
    {
        $this->expectException(MysqliException::class);

        $this->getConnection(['hello' => 'world']); // use local infile
    }

    /**
     * @param mixed[] $driverOptions
     */
    private function getConnection(array $driverOptions): MysqliConnection
    {
        return new MysqliConnection(
            [
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port' => (int) $GLOBALS['db_port'],
            ],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $driverOptions
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Connection;
use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function array_merge;

use const MYSQLI_OPT_CONNECT_TIMEOUT;

/** @require extension mysqli */
class ConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('mysqli')) {
            return;
        }

        self::markTestSkipped('This test requires the mysqli driver.');
    }

    public function testSupportedDriverOptions(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getConnection([MYSQLI_OPT_CONNECT_TIMEOUT => 1]);
    }

    public function testUnsupportedDriverOption(): void
    {
        $this->expectException(Exception::class);

        $this->getConnection([12345 => 'world']);
    }

    public function testInvalidCharset(): void
    {
        $params = TestUtil::getConnectionParams();

        $this->expectException(Exception::class);
        (new Driver())->connect(
            array_merge(
                $params,
                ['charset' => 'invalid'],
            ),
        );
    }

    /** @param array<string, mixed> $driverOptions */
    private function getConnection(array $driverOptions): Connection
    {
        $params = TestUtil::getConnectionParams();

        if (isset($params['driverOptions'])) {
            // Currently, MySQLi driver options may be either numeric MYSQLI_* keys
            // or the {@link Connection::OPTION_FLAGS} string key.
            // The options should be merged using array union instead of array_merge()
            // to preserve the numeric keys.
            $driverOptions += $params['driverOptions'];
        }

        return (new Driver())->connect(
            array_merge(
                $params,
                ['driverOptions' => $driverOptions],
            ),
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Connection;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

use function array_merge;

#[RequiresPhpExtension('pdo_sqlsrv')]
class DriverTest extends AbstractDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_sqlsrv')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_sqlsrv driver.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'master';
    }

    /** @param int[]|string[] $driverOptions */
    private function getConnection(array $driverOptions): Connection
    {
        $params = TestUtil::getConnectionParams();

        if (isset($params['driverOptions'])) {
            $driverOptions = array_merge($params['driverOptions'], $driverOptions);
        }

        return (new Driver())->connect(
            array_merge(
                $params,
                ['driverOptions' => $driverOptions],
            ),
        );
    }

    public function testConnectionOptions(): void
    {
        $connection = $this->getConnection(['APP' => 'APP_NAME']);
        $result     = $connection->query('SELECT APP_NAME()')->fetchOne();

        self::assertSame('APP_NAME', $result);
    }

    public function testDriverOptions(): void
    {
        $connection = $this->getConnection([PDO::ATTR_CASE => PDO::CASE_UPPER]);

        self::assertSame(
            PDO::CASE_UPPER,
            $connection
                ->getNativeConnection()
                ->getAttribute(PDO::ATTR_CASE),
        );
    }
}

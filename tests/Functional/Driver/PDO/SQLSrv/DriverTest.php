<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Connection;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractDriverTest;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PDO;

use function array_merge;

/**
 * @requires extension pdo_sqlsrv
 */
class DriverTest extends AbstractDriverTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }


        self::markTestSkipped('pdo_sqlsrv only test.');
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter(): ?string
    {
        return 'master';
    }

    /**
     * @param int[]|string[] $driverOptions
     */
    private function getConnection(array $driverOptions): Connection
    {
        return (new Driver())->connect(
            array_merge(
                TestUtil::getConnectionParams(),
                ['driverOptions' => $driverOptions]
            )
        );
    }

    public function testConnectionOptions(): void
    {
        $connection = $this->getConnection(['APP' => 'APP_NAME']);
        $result = $connection->query('SELECT APP_NAME()')->fetchOne();

        self::assertSame('APP_NAME', $result);
    }

    public function testDriverOptions(): void
    {
        $connection = $this->getConnection([PDO::ATTR_CASE => PDO::CASE_UPPER]);

        self::assertSame(
            PDO::CASE_UPPER,
            $connection
                ->getWrappedConnection()
                ->getAttribute(PDO::ATTR_CASE)
        );
    }

    /**
     * @dataProvider stringDataProvider
     */
    public function testDriverStringBinding(string $bindingType, string $expectedSqlType): void
    {
        $statement = $this->connection->prepare(
            'SELECT sql_variant_property(:parameter, \'BaseType\') AS type'
        );

        $statement->bindValue(':parameter', 'TEST', $bindingType);
        $result = $statement->execute();

        self::assertEquals($expectedSqlType, $result->fetchAssociative()['type']);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function stringDataProvider(): array
    {
        return [
            [Types::VARCHAR, 'varchar'],
            [Types::STRING, 'nvarchar']
        ];
    }
}

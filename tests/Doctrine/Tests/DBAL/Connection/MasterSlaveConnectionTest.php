<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Connection;

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\Tests\DbalTestCase;

class MasterSlaveConnectionTest extends DbalTestCase
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public static function getQueryMethods(): iterable
    {
        return [
            ['exec'],
            ['query'],
            ['executeQuery'],
            ['executeUpdate'],
            ['prepare'],
        ];
    }

    /**
     * @requires extension pdo_sqlite
     * @dataProvider getQueryMethods
     */
    public function testDriverExceptionIsWrapped(string $method): void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage(
            <<<EOF
An exception occurred while executing 'MUUHAAAAHAAAA':

SQLSTATE[HY000]: General error: 1 near "MUUHAAAAHAAAA"
EOF
        );

        $connection = DriverManager::getConnection(
            [
                'wrapperClass' => MasterSlaveConnection::class,
                'memory' => true,
                'driver' => 'pdo_sqlite',
                'master' => [],
                'slaves' => ['slave1' => ['driver' => 'pdo_sqlite']],
            ]
        );

        $connection->$method('MUUHAAAAHAAAA');
    }
}

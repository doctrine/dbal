<?php

namespace Doctrine\Tests\DBAL\Connections;

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\Driver;
use Doctrine\Tests\DbalTestCase;

class MasterSlaveConnectionTest extends DbalTestCase
{
    public function testConnectionParamsRemainAvailable(): void
    {
        $constructionParams = [
            'driver' => 'pdo_mysql',
            'keepSlave' => true,
            'master' => [
                'host' => 'master.host',
                'user' => 'root',
                'password' => 'password',
                'port' => '1234',
            ],
            'slaves' => [
                [
                    'host' => 'slave1.host',
                    'user' => 'root',
                    'password' => 'password',
                    'port' => '1234',
                ],
            ],
        ];

        $connection = new MasterSlaveConnection($constructionParams, $this->createStub(Driver::class));

        $connectionParams = $connection->getParams();
        foreach ($constructionParams as $key => $value) {
            self::assertSame($value, $connectionParams[$key]);
        }
    }
}

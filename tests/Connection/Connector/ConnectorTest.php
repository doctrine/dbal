<?php

namespace Doctrine\Tests\DBAL\Connection\Connector;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\Tests\DbalTestCase;
use function array_rand;
use function is_array;

abstract class ConnectorTest extends DbalTestCase
{
    /**
     * @return array
     */
    protected function getParams(int $slaves = 2, int $failSlaves = 0, bool $failMaster = false) : array
    {
        $params = [
            'master' => [
                'driver' => 'pdo_sqlite',
                'user' => 'root',
                'password' => '',
                'memory' => true,
                'fail' => $failMaster,
            ],
            'slaves' => [],
        ];

        for ($i = 0; $i < $slaves; $i++) {
            $params['slaves'][] = [
                'driver' => 'pdo_sqlite',
                'user' => 'root',
                'password' => '',
                'memory' => true,
                'fail' => false,
            ];
        }

        if ($failSlaves > 0) {
            $slaves = array_rand($params['slaves'], $failSlaves);

            if (! is_array($slaves)) {
                $slaves = [$slaves];
            }

            foreach ($slaves as $slaveIndex) {
                $params['slaves'][$slaveIndex]['fail'] = true;
            }
        }

        return $params;
    }

    protected function failingDriverMock()
    {
        $driverMock = $this->createMock(Driver::class);

        $driverMock->expects($this->any())
            ->method('connect')
            ->will($this->returnCallback(function (array $params) {
                if (isset($params['fail']) && $params['fail']) {
                    throw $this->createMock(ConnectionException::class);
                }

                return $this->createMock(DriverConnection::class);
            }));

        return $driverMock;
    }
}

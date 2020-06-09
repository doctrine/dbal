<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDB2Driver;
use Doctrine\DBAL\Driver\Connection;

use function array_keys;
use function array_map;
use function implode;
use function sprintf;

/**
 * IBM DB2 Driver.
 */
final class DB2Driver extends AbstractDB2Driver
{
    /**
     * {@inheritdoc}
     */
    public function connect(
        array $params,
        string $username = '',
        string $password = '',
        array $driverOptions = []
    ): Connection {
        if ($params['host'] !== 'localhost' && $params['host'] !== '127.0.0.1') {
            // if the host isn't localhost, use extended connection params
            $params['dbname'] = $this->buildConnectionString($params, $username, $password);

            $username = $password = '';
        }

        return new DB2Connection($params, $username, $password, $driverOptions);
    }

    /**
     * @param string[] $params
     */
    private function buildConnectionString(array $params, string $username, string $password): string
    {
        $connectionParams = [
            'DRIVER'   => '{IBM DB2 ODBC DRIVER}',
            'DATABASE' => $params['dbname'],
            'HOSTNAME' => $params['host'],
            'PROTOCOL' => $params['protocol'] ?? 'TCPIP',
            'UID'      => $username,
            'PWD'      => $password,
        ];

        if (isset($params['port'])) {
            $connectionParams['PORT'] = $params['port'];
        }

        return implode(';', array_map(static function (string $key, string $value): string {
            return sprintf('%s=%s', $key, $value);
        }, array_keys($connectionParams), $connectionParams));
    }
}

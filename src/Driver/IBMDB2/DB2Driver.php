<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDB2Driver;
use Doctrine\DBAL\Driver\Connection;

/**
 * IBM DB2 Driver.
 */
final class DB2Driver extends AbstractDB2Driver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        return new DB2Connection(
            DataSourceName::fromConnectionParameters($params)->toString(),
            isset($params['persistent']) && $params['persistent'] === true,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $params['driver_options'] ?? []
        );
    }
}

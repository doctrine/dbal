<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDB2Driver;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;

final class Driver extends AbstractDB2Driver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): ConnectionInterface
    {
        return new Connection(
            DataSourceName::fromConnectionParameters($params)->toString(),
            isset($params['persistent']) && $params['persistent'] === true,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $params['driver_options'] ?? []
        );
    }
}

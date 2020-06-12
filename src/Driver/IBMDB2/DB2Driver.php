<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDB2Driver;

/**
 * IBM DB2 Driver.
 */
class DB2Driver extends AbstractDB2Driver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params)
    {
        return new DB2Connection(
            DataSourceName::fromConnectionParameters($params)->toString(),
            isset($params['persistent']) && $params['persistent'] === true,
            $params['user'] ?? '',
            $params['password'] ?? '',
            $params['driver_options'] ?? []
        );
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     */
    public function getName()
    {
        return 'ibm_db2';
    }
}

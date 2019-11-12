<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\Connection;
use const OCI_DEFAULT;

/**
 * A Doctrine DBAL driver for the Oracle OCI8 PHP extensions.
 */
class Driver extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(
        array $params,
        string $username = '',
        string $password = '',
        array $driverOptions = []
    ) : Connection {
        try {
            return new OCI8Connection(
                $username,
                $password,
                $this->_constructDsn($params),
                $params['charset'] ?? '',
                $params['sessionMode'] ?? OCI_DEFAULT,
                $params['persistent'] ?? false
            );
        } catch (OCI8Exception $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the Oracle DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    protected function _constructDsn(array $params) : string
    {
        return $this->getEasyConnectString($params);
    }
}

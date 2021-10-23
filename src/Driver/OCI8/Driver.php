<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\AbstractOracleDriver;

use const OCI_NO_AUTO_COMMIT;

/**
 * A Doctrine DBAL driver for the Oracle OCI8 PHP extensions.
 */
final class Driver extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        return new Connection(
            $params['user'] ?? '',
            $params['password'] ?? '',
            $this->constructDsn($params),
            $params['charset'] ?? '',
            $params['sessionMode'] ?? OCI_NO_AUTO_COMMIT,
            $params['persistent'] ?? false
        );
    }

    /**
     * Constructs the Oracle DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    private function constructDsn(array $params): string
    {
        return $this->getEasyConnectString($params);
    }
}

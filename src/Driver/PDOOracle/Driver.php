<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOOracle;

use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use PDO;

/**
 * PDO Oracle driver.
 *
 * WARNING: This driver gives us segfaults in our testsuites on CLOB and other
 * stuff. PDO Oracle is not maintained by Oracle or anyone in the PHP community,
 * which leads us to the recommendation to use the "oci8" driver to connect
 * to Oracle instead.
 */
final class Driver extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params): Connection
    {
        $driverOptions = $params['driver_options'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        return new PDOConnection(
            $this->constructPdoDsn($params),
            $params['user'] ?? '',
            $params['password'] ?? '',
            $driverOptions
        );
    }

    /**
     * Constructs the Oracle PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'oci:dbname=' . $this->getEasyConnectString($params);

        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }

        return $dsn;
    }
}

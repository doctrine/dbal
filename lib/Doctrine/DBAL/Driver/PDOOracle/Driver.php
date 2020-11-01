<?php

namespace Doctrine\DBAL\Driver\PDOOracle;

use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\Exception;
use PDOException;

/**
 * PDO Oracle driver.
 *
 * @deprecated Use {@link PDO\OCI\Driver} instead.
 */
class Driver extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        try {
            return new PDO\Connection(
                $this->constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        } catch (PDOException $e) {
            throw Exception::driverException($this, $e);
        }
    }

    /**
     * Constructs the Oracle PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    private function constructPdoDsn(array $params)
    {
        $dsn = 'oci:dbname=' . $this->getEasyConnectString($params);

        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_oracle';
    }
}

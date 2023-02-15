<?php

namespace Doctrine\DBAL\Driver\PDO\IBMDB2;

use Doctrine\DBAL\Driver\AbstractDB2Driver;
use Doctrine\DBAL\Driver\IBMDB2\DataSourceName;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use PDO;
use PDOException;
use SensitiveParameter;

final class Driver extends AbstractDB2Driver
{
    public function connect(
        #[SensitiveParameter]
        array $params
    ): Connection {
        $driverOptions = $params['driverOptions'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        $dataSourceName = 'ibm:';
        $dataSourceName .= DataSourceName::fromConnectionParameters($params)->toString();

        try {
            $pdo = new PDO(
                $dataSourceName,
                $params['user'],
                $params['password'],
                // $driverOptions,
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        return new Connection($pdo);
    }

    /**
     * Constructs the IBM DB2 PDO DSN.
     *
     * @param mixed[] $params
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'ibm:';
        if ($params['host'] ?? null !== '') {
            $dsn .= 'HOSTNAME=' . $params['host'] . ';';
        }

        if (isset($params['port'])) {
            $dsn .= 'PORT=' . $params['port'] . ';';
        }

        if (isset($params['dbname'])) {
            $dsn .= 'DATABASE=' . $params['dbname'] . ';';
        }

        if (isset($params['protocol'])) {
            $dsn .= 'PROTOCOL=' . $params['protocol'] . ';';
        }

        return $dsn;
    }
}

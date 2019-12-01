<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlite;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\PDOException;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use function array_merge;

/**
 * The PDO Sqlite driver.
 */
class Driver extends AbstractSQLiteDriver
{
    /** @var mixed[] */
    protected $_userDefinedFunctions = [
        'sqrt' => ['callback' => [SqlitePlatform::class, 'udfSqrt'], 'numArgs' => 1],
        'mod'  => ['callback' => [SqlitePlatform::class, 'udfMod'], 'numArgs' => 2],
        'locate'  => ['callback' => [SqlitePlatform::class, 'udfLocate'], 'numArgs' => -1],
    ];

    /**
     * {@inheritdoc}
     */
    public function connect(
        array $params,
        string $username = '',
        string $password = '',
        array $driverOptions = []
    ) : DriverConnection {
        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->_userDefinedFunctions = array_merge(
                $this->_userDefinedFunctions,
                $driverOptions['userDefinedFunctions']
            );
            unset($driverOptions['userDefinedFunctions']);
        }

        try {
            $connection = new Connection(
                $this->_constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        } catch (PDOException $ex) {
            throw DBALException::driverException($this, $ex);
        }

        $pdo = $connection->getWrappedConnection();

        foreach ($this->_userDefinedFunctions as $fn => $data) {
            $pdo->sqliteCreateFunction($fn, $data['callback'], $data['numArgs']);
        }

        return $connection;
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    protected function _constructPdoDsn(array $params) : string
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }
}

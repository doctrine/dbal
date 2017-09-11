<?php

namespace Doctrine\DBAL\Driver\PDOSqlite;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\PDOConnection;
use PDOException;
use function array_merge;

/**
 * The PDO Sqlite driver.
 *
 * @since 2.0
 */
class Driver extends AbstractSQLiteDriver
{
    /**
     * @var array
     */
    protected $_userDefinedFunctions = [
        'sqrt' => ['callback' => ['Doctrine\DBAL\Platforms\SqlitePlatform', 'udfSqrt'], 'numArgs' => 1],
        'mod'  => ['callback' => ['Doctrine\DBAL\Platforms\SqlitePlatform', 'udfMod'], 'numArgs' => 2],
        'locate'  => ['callback' => ['Doctrine\DBAL\Platforms\SqlitePlatform', 'udfLocate'], 'numArgs' => -1],
    ];

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->_userDefinedFunctions = array_merge(
                $this->_userDefinedFunctions, $driverOptions['userDefinedFunctions']);
            unset($driverOptions['userDefinedFunctions']);
        }

        try {
            $pdo = new PDOConnection(
                $this->_constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        } catch (PDOException $ex) {
            throw DBALException::driverException($this, $ex);
        }

        foreach ($this->_userDefinedFunctions as $fn => $data) {
            $pdo->sqliteCreateFunction($fn, $data['callback'], $data['numArgs']);
        }

        return $pdo;
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    protected function _constructPdoDsn(array $params)
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } elseif (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlite';
    }
}

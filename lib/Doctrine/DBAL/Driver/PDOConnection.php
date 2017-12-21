<?php

namespace Doctrine\DBAL\Driver;

use PDO;

/**
 * PDO implementation of the Connection interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOConnection extends PDO implements Connection, ServerInfoAwareConnection
{
    /**
     * @param string      $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null  $options
     *
     * @throws PDOException in case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        try {
            parent::__construct($dsn, $user, $password, $options);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['Doctrine\DBAL\Driver\PDOStatement', []]);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        try {
            return parent::exec($statement);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return PDO::getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString, $driverOptions = [])
    {
        try {
            return parent::prepare($prepareString, $driverOptions);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $argsCount = count($args);

        try {
            if ($argsCount == 4) {
                return parent::query($args[0], $args[1], $args[2], $args[3]);
            }

            if ($argsCount == 3) {
                return parent::query($args[0], $args[1], $args[2]);
            }

            if ($argsCount == 2) {
                return parent::query($args[0], $args[1]);
            }

            return parent::query($args[0]);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = \PDO::PARAM_STR)
    {
        return parent::quote($input, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return parent::lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }
}

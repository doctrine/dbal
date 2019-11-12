<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use PDO;
use const CASE_LOWER;
use const CASE_UPPER;

/**
 * Portability wrapper for a Connection.
 */
class Connection extends \Doctrine\DBAL\Connection
{
    public const PORTABILITY_ALL           = 255;
    public const PORTABILITY_NONE          = 0;
    public const PORTABILITY_RTRIM         = 1;
    public const PORTABILITY_EMPTY_TO_NULL = 4;
    public const PORTABILITY_FIX_CASE      = 8;

    public const PORTABILITY_DB2          = 13;
    public const PORTABILITY_ORACLE       = 9;
    public const PORTABILITY_POSTGRESQL   = 13;
    public const PORTABILITY_SQLITE       = 13;
    public const PORTABILITY_OTHERVENDORS = 12;
    public const PORTABILITY_SQLANYWHERE  = 13;
    public const PORTABILITY_SQLSRV       = 13;

    /** @var int */
    private $portability = self::PORTABILITY_NONE;

    /** @var int */
    private $case;

    /**
     * {@inheritdoc}
     */
    public function connect() : void
    {
        if ($this->isConnected()) {
            return;
        }

        parent::connect();

        $params = $this->getParams();

        if (isset($params['portability'])) {
            if ($this->getDatabasePlatform()->getName() === 'oracle') {
                $params['portability'] &= self::PORTABILITY_ORACLE;
            } elseif ($this->getDatabasePlatform()->getName() === 'postgresql') {
                $params['portability'] &= self::PORTABILITY_POSTGRESQL;
            } elseif ($this->getDatabasePlatform()->getName() === 'sqlite') {
                $params['portability'] &= self::PORTABILITY_SQLITE;
            } elseif ($this->getDatabasePlatform()->getName() === 'sqlanywhere') {
                $params['portability'] &= self::PORTABILITY_SQLANYWHERE;
            } elseif ($this->getDatabasePlatform()->getName() === 'db2') {
                $params['portability'] &= self::PORTABILITY_DB2;
            } elseif ($this->getDatabasePlatform()->getName() === 'mssql') {
                $params['portability'] &= self::PORTABILITY_SQLSRV;
            } else {
                $params['portability'] &= self::PORTABILITY_OTHERVENDORS;
            }
            $this->portability = $params['portability'];
        }

        if (! isset($params['fetch_case']) || ! ($this->portability & self::PORTABILITY_FIX_CASE)) {
            return;
        }

        if ($this->_conn instanceof PDOConnection) {
            // make use of c-level support for case handling
            $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_CASE, $params['fetch_case']);
        } else {
            $this->case = $params['fetch_case'] === ColumnCase::LOWER ? CASE_LOWER : CASE_UPPER;
        }
    }

    public function getPortability() : int
    {
        return $this->portability;
    }

    public function getFetchCase() : ?int
    {
        return $this->case;
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(string $query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null) : ResultStatement
    {
        $stmt = new Statement(parent::executeQuery($query, $params, $types, $qcp), $this);
        $stmt->setFetchMode($this->defaultFetchMode);

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        $stmt = new Statement(parent::prepare($sql), $this);
        $stmt->setFetchMode($this->defaultFetchMode);

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
    {
        $connection = $this->getWrappedConnection();

        $stmt = $connection->query($sql);
        $stmt = new Statement($stmt, $this);
        $stmt->setFetchMode($this->defaultFetchMode);

        return $stmt;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Abstraction\Result as AbstractionResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Result as DBALResult;
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

    /** @var Converter */
    private $converter;

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        parent::connect();

        $params      = $this->getParams();
        $portability = self::PORTABILITY_NONE;

        if (isset($params['portability'])) {
            if ($this->getDatabasePlatform()->getName() === 'oracle') {
                $portability = $params['portability'] & self::PORTABILITY_ORACLE;
            } elseif ($this->getDatabasePlatform()->getName() === 'postgresql') {
                $portability = $params['portability'] & self::PORTABILITY_POSTGRESQL;
            } elseif ($this->getDatabasePlatform()->getName() === 'sqlite') {
                $portability = $params['portability'] & self::PORTABILITY_SQLITE;
            } elseif ($this->getDatabasePlatform()->getName() === 'sqlanywhere') {
                $portability = $params['portability'] & self::PORTABILITY_SQLANYWHERE;
            } elseif ($this->getDatabasePlatform()->getName() === 'db2') {
                $portability = $params['portability'] & self::PORTABILITY_DB2;
            } elseif ($this->getDatabasePlatform()->getName() === 'mssql') {
                $portability = $params['portability'] & self::PORTABILITY_SQLSRV;
            } else {
                $portability = $params['portability'] & self::PORTABILITY_OTHERVENDORS;
            }
        }

        $case = null;

        if (isset($params['fetch_case']) && ($portability & self::PORTABILITY_FIX_CASE) !== 0) {
            if ($this->_conn instanceof PDOConnection) {
                // make use of c-level support for case handling
                $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_CASE, $params['fetch_case']);
            } else {
                $case = $params['fetch_case'] === ColumnCase::LOWER ? CASE_LOWER : CASE_UPPER;
            }
        }

        $this->converter = new Converter(
            ($portability & self::PORTABILITY_EMPTY_TO_NULL) !== 0,
            ($portability & self::PORTABILITY_RTRIM) !== 0,
            $case
        );
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(
        string $query,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null
    ): AbstractionResult {
        return $this->wrapResult(
            parent::executeQuery($query, $params, $types, $qcp)
        );
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(parent::prepare($sql), $this->converter);
    }

    public function query(string $sql): DriverResult
    {
        return $this->wrapResult(
            parent::query($sql)
        );
    }

    private function wrapResult(DriverResult $result): AbstractionResult
    {
        return new DBALResult(
            new Result($result, $this->converter),
            $this
        );
    }
}

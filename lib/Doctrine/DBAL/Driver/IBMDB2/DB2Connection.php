<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use stdClass;
use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;
use function db2_autocommit;
use function db2_commit;
use function db2_conn_error;
use function db2_conn_errormsg;
use function db2_connect;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_pconnect;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;
use function db2_stmt_errormsg;

class DB2Connection implements Connection, ServerInfoAwareConnection
{
    /** @var resource */
    private $conn = null;

    /**
     * @param mixed[] $params
     * @param string  $username
     * @param string  $password
     * @param mixed[] $driverOptions
     *
     * @throws DB2Exception
     */
    public function __construct(array $params, $username, $password, $driverOptions = [])
    {
        $isPersistent = (isset($params['persistent']) && $params['persistent'] === true);

        if ($isPersistent) {
            $conn = db2_pconnect($params['dbname'], $username, $password, $driverOptions);
        } else {
            $conn = db2_connect($params['dbname'], $username, $password, $driverOptions);
        }

        if ($conn === false) {
            throw new DB2Exception(db2_conn_errormsg());
        }

        $this->conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        /** @var stdClass $serverInfo */
        $serverInfo = db2_server_info($this->conn);

        return $serverInfo->DBMS_VER;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        $stmt = @db2_prepare($this->conn, $sql);
        if (! $stmt) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        return new DB2Statement($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        $input = db2_escape_string($input);

        if ($type === ParameterType::INTEGER) {
            return $input;
        }

        return "'" . $input . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement) : int
    {
        $stmt = @db2_exec($this->conn, $statement);

        if ($stmt === false) {
            throw new DB2Exception(db2_stmt_errormsg());
        }

        return db2_num_rows($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        return db2_last_insert_id($this->conn);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction() : void
    {
        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_OFF)) {
            throw new DB2Exception(db2_conn_errormsg($this->conn));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit() : void
    {
        if (! db2_commit($this->conn)) {
            throw new DB2Exception(db2_conn_errormsg($this->conn));
        }

        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON)) {
            throw new DB2Exception(db2_conn_errormsg($this->conn));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack() : void
    {
        if (! db2_rollback($this->conn)) {
            throw new DB2Exception(db2_conn_errormsg($this->conn));
        }

        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON)) {
            throw new DB2Exception(db2_conn_errormsg($this->conn));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return db2_conn_error($this->conn);
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return [
            0 => db2_conn_errormsg($this->conn),
            1 => $this->errorCode(),
        ];
    }
}

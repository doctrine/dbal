<?php

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use PDO;
use function array_key_exists;
use function is_int;
use function stripos;

/**
 * PDO SQL Server Statement
 */
class PDOSqlsrvStatement implements IteratorAggregate, Statement
{
    /**
     * The PDO Connection.
     *
     * @var PDOConnection
     */
    private $conn;

    /**
     * The SQL statement to execute.
     *
     * @var string
     */
    private $sql;

    /**
     * The PDO statement.
     *
     * @var PDOStatement
     */
    private $stmt;

    /**
     * The last insert ID.
     *
     * @var LastInsertId|null
     */
    private $lastInsertId;

    /**
     * The affected number of rows
     *
     * @var int|null
     */
    private $rowCount;

    /**
     * Append to any INSERT query to retrieve the last insert id.
     */
    public const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';

    /**
     * @param string $sql
     */
    public function __construct(PDOConnection $conn, $sql, ?LastInsertId $lastInsertId = null)
    {
        $this->conn = $conn;
        $this->sql  = $sql;

        if (stripos($sql, 'INSERT INTO ') === 0) {
            $this->sql         .= self::LAST_INSERT_ID_SQL;
            $this->lastInsertId = $lastInsertId;
        }

        $this->stmt = $this->prepare();
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        if (($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        return $this->stmt->bindParam($column, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                if ($hasZeroIndex && is_int($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        $result         = $this->stmt->execute($params);
        $this->rowCount = $this->rowCount();

        if (! $this->lastInsertId) {
            return $result;
        }

        $id = null;
        $this->stmt->nextRowset();

        if ($this->columnCount() > 0) {
            $id = $this->fetchColumn();
        }

        if (! $id) {
            while ($this->stmt->nextRowset()) {
                if ($this->columnCount() === 0) {
                    continue;
                }

                $id = $this->fetchColumn();
            }
        }

        $this->lastInsertId->setId($id);

        return $result;
    }

    /**
     * Prepares PDO statement resource
     *
     * @return PDOStatement
     */
    private function prepare()
    {
        /** @var PDOStatement $stmt */
        $stmt = $this->conn->prepare($this->sql);

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return $this->rowCount ?: $this->stmt->rowCount();
    }
}

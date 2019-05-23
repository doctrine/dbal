<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use PDO;
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

    public function __construct(PDOConnection $conn, string $sql, ?LastInsertId $lastInsertId = null)
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
    public function bindValue($param, $value, $type = ParameterType::STRING) : void
    {
        $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        $driverOptions = null;

        if ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        $this->stmt->bindParam($param, $variable, $type, $length, $driverOptions);
    }

        /**
         * {@inheritdoc}
         */
    public function closeCursor() : void
    {
        $this->stmt->closeCursor();
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount() : int
    {
        return $this->stmt->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        $this->stmt->execute($params);
        $this->rowCount = $this->rowCount();

        if (! $this->lastInsertId) {
            return;
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
    public function setFetchMode($fetchMode, ...$args) : void
    {
        $this->stmt->setFetchMode($fetchMode, ...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        yield from $this->stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(?int $fetchMode = null, ...$args)
    {
        return $this->stmt->fetch($fetchMode, ...$args);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
    {
        return $this->stmt->fetchAll($fetchMode, ...$args);
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
    public function rowCount() : int
    {
        return $this->rowCount ?: $this->stmt->rowCount();
    }

    public function nextRowset() : bool
    {
        return $this->stmt->nextRowset();
    }
}

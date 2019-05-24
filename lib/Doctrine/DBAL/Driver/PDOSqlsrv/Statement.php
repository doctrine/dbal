<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\ParameterType;
use PDO;

/**
 * PDO SQL Server Statement
 */
class Statement extends PDOStatement
{
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

    public function __construct(\PDOStatement $stmt, ?LastInsertId $lastInsertId = null)
    {
        parent::__construct($stmt);
        $this->lastInsertId = $lastInsertId;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null, $driverOptions = null) : void
    {
        if (($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY)
            && $driverOptions === null
        ) {
            $driverOptions = PDO::SQLSRV_ENCODING_BINARY;
        }

        parent::bindParam($param, $variable, $type, $length, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        parent::execute($params);
        $this->rowCount = $this->rowCount();

        if (! $this->lastInsertId) {
            return;
        }

        $id = null;
        $this->nextRowset();

        if ($this->columnCount() > 0) {
            $id = $this->fetchColumn();
        }

        if (! $id) {
            while ($this->nextRowset()) {
                if ($this->columnCount() === 0) {
                    continue;
                }

                $id = $this->fetchColumn();
            }
        }

        $this->lastInsertId->setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount() : int
    {
        return $this->rowCount ?: parent::rowCount();
    }
}

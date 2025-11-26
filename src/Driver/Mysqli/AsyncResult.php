<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use mysqli;
use mysqli_result;

use const MYSQLI_ASSOC;
use const MYSQLI_NUM;

/**
 * Result class for async queries executed via mysqli.
 *
 * This is different from the regular Result class because async queries
 * use mysqli::query() which returns mysqli_result, not mysqli_stmt.
 */
final class AsyncResult implements ResultInterface
{
    private ?mysqli_result $result;
    private mysqli $connection;

    /**
     * @internal The result can be only instantiated by the driver connection.
     *
     * @param mysqli_result|null $result     The result set, or null for non-SELECT queries
     * @param mysqli             $connection The connection for affected_rows access
     */
    public function __construct(?mysqli_result $result, mysqli $connection)
    {
        $this->result     = $result;
        $this->connection = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchNumeric()
    {
        if ($this->result === null) {
            return false;
        }

        $row = $this->result->fetch_row();

        return $row ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssociative()
    {
        if ($this->result === null) {
            return false;
        }

        $row = $this->result->fetch_assoc();

        return $row ?? false;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        if ($this->result === null) {
            return [];
        }

        return $this->result->fetch_all(MYSQLI_NUM);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        if ($this->result === null) {
            return [];
        }

        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function rowCount(): int
    {
        if ($this->result !== null) {
            return $this->result->num_rows;
        }

        return $this->connection->affected_rows;
    }

    public function columnCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return $this->result->field_count;
    }

    public function free(): void
    {
        if ($this->result === null) {
            return;
        }

        $this->result->free();
    }
}


<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use ValueError;

use const MYSQLI_ASSOC;
use const MYSQLI_NUM;

final class Result implements ResultInterface
{
    private mysqli_result|false $result;

    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @param Statement|null $statementReference Maintains a reference to the Statement that generated this result. This
     *                                           ensures that the lifetime of the Statement is managed in conjunction
     *                                           with its associated results, so they are destroyed together at the
     *                                           appropriate time, see {@see Statement::__destruct()}.
     *
     * @throws Exception
     */
    public function __construct(
        private readonly mysqli_stmt $statement,
        private ?Statement $statementReference = null, // @phpstan-ignore property.onlyWritten
    ) {
        try {
            $this->result = $statement->get_result();
        } catch (mysqli_sql_exception $exception) {
            throw StatementError::upcast($exception);
        }
    }

    public function fetchNumeric(): array|false
    {
        if ($this->result === false) {
            return false;
        }

        /** @phpstan-ignore return.type */
        return $this->result->fetch_row() ?? false;
    }

    public function fetchAssociative(): array|false
    {
        if ($this->result === false) {
            return false;
        }

        return $this->result->fetch_assoc() ?? false;
    }

    public function fetchOne(): mixed
    {
        if ($this->result === false) {
            return false;
        }

        return $this->result->fetch_column();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        if ($this->result === false) {
            return [];
        }

        /** @phpstan-ignore return.type */
        return $this->result->fetch_all(MYSQLI_NUM);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        if ($this->result === false) {
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

    public function rowCount(): int|string
    {
        if ($this->result !== false) {
            return $this->result->num_rows;
        }

        return $this->statement->affected_rows;
    }

    public function columnCount(): int
    {
        if ($this->result === false) {
            return 0;
        }

        return $this->result->field_count;
    }

    public function getColumnName(int $index): string
    {
        if ($this->result === false) {
            throw InvalidColumnIndex::new($index);
        }

        try {
            /** @phpstan-ignore property.nonObject */
            return $this->result->fetch_field_direct($index)->name;
        } catch (ValueError $exception) {
            throw InvalidColumnIndex::new($index, $exception);
        }
    }

    public function free(): void
    {
        $this->result = false;
    }
}

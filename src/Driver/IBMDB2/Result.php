<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

use function db2_fetch_array;
use function db2_fetch_assoc;
use function db2_field_name;
use function db2_free_result;
use function db2_num_fields;
use function db2_num_rows;
use function db2_stmt_error;

final class Result implements ResultInterface
{
    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @param resource $statement
     */
    public function __construct(private readonly mixed $statement)
    {
    }

    public function fetchNumeric(): array|false
    {
        $row = @db2_fetch_array($this->statement);

        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw StatementError::new($this->statement);
        }

        return $row;
    }

    public function fetchAssociative(): array|false
    {
        $row = @db2_fetch_assoc($this->statement);

        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw StatementError::new($this->statement);
        }

        return $row;
    }

    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
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
        $numRows = @db2_num_rows($this->statement);

        if ($numRows === false) {
            throw StatementError::new($this->statement);
        }

        return $numRows;
    }

    public function columnCount(): int
    {
        $count = db2_num_fields($this->statement);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    public function getColumnName(int $index): string
    {
        $name = db2_field_name($this->statement, $index);

        if ($name === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $name;
    }

    public function free(): void
    {
        db2_free_result($this->statement);
    }
}

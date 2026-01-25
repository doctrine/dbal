<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\IBMDB2\Exception\StatementError;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Override;

use function db2_fetch_array;
use function db2_fetch_assoc;
use function db2_field_name;
use function db2_free_result;
use function db2_num_fields;
use function db2_num_rows;
use function db2_stmt_error;

final readonly class Result implements ResultInterface
{
    /**
     * @internal The result can be only instantiated by its driver connection or statement.
     *
     * @param resource $statement
     */
    public function __construct(private mixed $statement)
    {
    }

    #[Override]
    public function fetchNumeric(): array|false
    {
        $row = @db2_fetch_array($this->statement);

        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw StatementError::new($this->statement);
        }

        return $row;
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        $row = @db2_fetch_assoc($this->statement);

        if ($row === false && db2_stmt_error($this->statement) !== '02000') {
            throw StatementError::new($this->statement);
        }

        return $row;
    }

    #[Override]
    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    #[Override]
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    #[Override]
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    #[Override]
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    #[Override]
    public function rowCount(): int
    {
        $numRows = @db2_num_rows($this->statement);

        if ($numRows === false) {
            throw StatementError::new($this->statement);
        }

        return $numRows;
    }

    #[Override]
    public function columnCount(): int
    {
        $count = db2_num_fields($this->statement);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    #[Override]
    public function getColumnName(int $index): string
    {
        $name = db2_field_name($this->statement, $index);

        if ($name === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $name;
    }

    #[Override]
    public function free(): void
    {
        db2_free_result($this->statement);
    }
}

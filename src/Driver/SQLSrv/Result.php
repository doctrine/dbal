<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

use function array_is_list;
use function assert;
use function sqlsrv_fetch;
use function sqlsrv_fetch_array;
use function sqlsrv_field_metadata;
use function sqlsrv_num_fields;
use function sqlsrv_rows_affected;

use const SQLSRV_FETCH_ASSOC;
use const SQLSRV_FETCH_NUMERIC;

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

    /** @return list<mixed>|false */
    public function fetchNumeric(): array|false
    {
        $result = $this->fetch(SQLSRV_FETCH_NUMERIC);
        if ($result === false || $result === null) {
            return false;
        }

        assert(array_is_list($result));

        return $result;
    }

    /** @return array<string, mixed>|false */
    public function fetchAssociative(): array|false
    {
        $result = $this->fetch(SQLSRV_FETCH_ASSOC);
        if ($result === false || $result === null) {
            return false;
        }

        return $result;
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
        $count = sqlsrv_rows_affected($this->statement);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    public function columnCount(): int
    {
        $count = sqlsrv_num_fields($this->statement);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    public function getColumnName(int $index): string
    {
        $meta = sqlsrv_field_metadata($this->statement);

        if ($meta === false || ! isset($meta[$index])) {
            throw InvalidColumnIndex::new($index);
        }

        return $meta[$index]['Name'];
    }

    public function free(): void
    {
        // emulate it by fetching and discarding rows, similarly to what PDO does in this case
        // @link http://php.net/manual/en/pdostatement.closecursor.php
        // @link https://github.com/php/php-src/blob/php-7.0.11/ext/pdo/pdo_stmt.c#L2075
        // deliberately do not consider multiple result sets, since doctrine/dbal doesn't support them
        while (sqlsrv_fetch($this->statement) === true) {
        }
    }

    /** @return array<mixed>|false|null */
    private function fetch(int $fetchType): array|false|null
    {
        return sqlsrv_fetch_array($this->statement, $fetchType);
    }
}

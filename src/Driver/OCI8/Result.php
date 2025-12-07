<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;

use function assert;
use function is_array;
use function oci_cancel;
use function oci_error;
use function oci_fetch_all;
use function oci_fetch_array;
use function oci_field_name;
use function oci_num_fields;
use function oci_num_rows;

use const OCI_ASSOC;
use const OCI_FETCHSTATEMENT_BY_COLUMN;
use const OCI_FETCHSTATEMENT_BY_ROW;
use const OCI_NUM;
use const OCI_RETURN_LOBS;
use const OCI_RETURN_NULLS;

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
        /** @var list<mixed> $result */
        $result = $this->fetch(OCI_NUM);

        if ($result === false) {
            return false;
        }

        assert(is_array($result));

        return $result;
    }

    public function fetchAssociative(): array|false
    {
        /** @var array<string, mixed> $result */
        $result = $this->fetch(OCI_ASSOC);

        if ($result === false) {
            return false;
        }

        assert(is_array($result));

        return $result;
    }

    public function fetchOne(): mixed
    {
        /** @var list<mixed>|false $row */
        $row = $this->fetch(OCI_NUM);

        return $row !== false ? $row[0] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(OCI_NUM, OCI_FETCHSTATEMENT_BY_ROW);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(OCI_ASSOC, OCI_FETCHSTATEMENT_BY_ROW);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        $result = $this->fetchAll(OCI_NUM, OCI_FETCHSTATEMENT_BY_COLUMN);

        /** @var list<mixed> $column */
        $column = $result[0] ?? [];

        return $column;
    }

    public function rowCount(): int
    {
        $count = oci_num_rows($this->statement);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    public function columnCount(): int
    {
        return oci_num_fields($this->statement);
    }

    public function getColumnName(int $index): string
    {
        // OCI expects a 1-based index while DBAL works with a O-based index.
        $name = @oci_field_name($this->statement, $index + 1);

        if ($name === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $name;
    }

    public function free(): void
    {
        oci_cancel($this->statement);
    }

    /** @throws Exception */
    private function fetch(int $mode): mixed
    {
        $result = oci_fetch_array($this->statement, $mode | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        if ($result === false && oci_error($this->statement) !== false) {
            throw Error::new($this->statement);
        }

        return $result;
    }

    /** @return array<mixed> */
    private function fetchAll(int $mode, int $fetchStructure): array
    {
        $result = [];

        oci_fetch_all(
            $this->statement,
            $result,
            0,
            -1,
            $mode | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS,
        );

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PgSQL;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\PgSQL\Exception\UnexpectedValue;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use PgSql\Result as PgSqlResult;
use ValueError;

use function array_keys;
use function array_map;
use function assert;
use function hex2bin;
use function pg_affected_rows;
use function pg_fetch_all;
use function pg_fetch_all_columns;
use function pg_fetch_assoc;
use function pg_fetch_row;
use function pg_field_name;
use function pg_field_type;
use function pg_free_result;
use function pg_num_fields;
use function substr;

use const PGSQL_ASSOC;
use const PGSQL_NUM;
use const PHP_INT_SIZE;

final class Result implements ResultInterface
{
    private ?PgSqlResult $result;

    public function __construct(PgSqlResult $result)
    {
        $this->result = $result;
    }

    public function __destruct()
    {
        if (! isset($this->result)) {
            return;
        }

        $this->free();
    }

    /** {@inheritDoc} */
    public function fetchNumeric(): array|false
    {
        if ($this->result === null) {
            return false;
        }

        $row = pg_fetch_row($this->result);
        if ($row === false) {
            return false;
        }

        return $this->mapNumericRow($row, $this->fetchNumericColumnTypes());
    }

    /** {@inheritDoc} */
    public function fetchAssociative(): array|false
    {
        if ($this->result === null) {
            return false;
        }

        $row = pg_fetch_assoc($this->result);
        if ($row === false) {
            return false;
        }

        return $this->mapAssociativeRow($row, $this->fetchAssociativeColumnTypes());
    }

    /** {@inheritDoc} */
    public function fetchOne(): mixed
    {
        return FetchUtils::fetchOne($this);
    }

    /** {@inheritDoc} */
    public function fetchAllNumeric(): array
    {
        if ($this->result === null) {
            return [];
        }

        $types = $this->fetchNumericColumnTypes();

        return array_map(
            fn (array $row) => $this->mapNumericRow($row, $types),
            pg_fetch_all($this->result, PGSQL_NUM),
        );
    }

    /** {@inheritDoc} */
    public function fetchAllAssociative(): array
    {
        if ($this->result === null) {
            return [];
        }

        $types = $this->fetchAssociativeColumnTypes();

        return array_map(
            fn (array $row) => $this->mapAssociativeRow($row, $types),
            pg_fetch_all($this->result, PGSQL_ASSOC),
        );
    }

    /** {@inheritDoc} */
    public function fetchFirstColumn(): array
    {
        if ($this->result === null) {
            return [];
        }

        $postgresType = pg_field_type($this->result, 0);

        return array_map(
            fn ($value) => $this->mapType($postgresType, $value),
            pg_fetch_all_columns($this->result),
        );
    }

    public function rowCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return pg_affected_rows($this->result);
    }

    public function columnCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return pg_num_fields($this->result);
    }

    public function getColumnName(int $index): string
    {
        if ($this->result === null) {
            throw InvalidColumnIndex::new($index);
        }

        try {
            return pg_field_name($this->result, $index);
        } catch (ValueError) {
            throw InvalidColumnIndex::new($index);
        }
    }

    public function free(): void
    {
        if ($this->result === null) {
            return;
        }

        pg_free_result($this->result);
        $this->result = null;
    }

    /** @return array<int, string> */
    private function fetchNumericColumnTypes(): array
    {
        assert($this->result !== null);

        $types     = [];
        $numFields = pg_num_fields($this->result);
        for ($i = 0; $i < $numFields; ++$i) {
            $types[$i] = pg_field_type($this->result, $i);
        }

        return $types;
    }

    /** @return array<string, string> */
    private function fetchAssociativeColumnTypes(): array
    {
        assert($this->result !== null);

        $types     = [];
        $numFields = pg_num_fields($this->result);
        for ($i = 0; $i < $numFields; ++$i) {
            $types[pg_field_name($this->result, $i)] = pg_field_type($this->result, $i);
        }

        return $types;
    }

    /**
     * @param list<string|null>  $row
     * @param array<int, string> $types
     *
     * @return list<mixed>
     */
    private function mapNumericRow(array $row, array $types): array
    {
        assert($this->result !== null);

        return array_map(
            fn ($value, $field) => $this->mapType($types[$field], $value),
            $row,
            array_keys($row),
        );
    }

    /**
     * @param array<string, string|null> $row
     * @param array<string, string>      $types
     *
     * @return array<string, mixed>
     */
    private function mapAssociativeRow(array $row, array $types): array
    {
        assert($this->result !== null);

        $mappedRow = [];
        foreach ($row as $field => $value) {
            $mappedRow[$field] = $this->mapType($types[$field], $value);
        }

        return $mappedRow;
    }

    private function mapType(string $postgresType, ?string $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        return match ($postgresType) {
            'bool' => match ($value) {
                't' => true,
                'f' => false,
                default => throw UnexpectedValue::new($value, $postgresType),
            },
            'bytea' => hex2bin(substr($value, 2)),
            'float4', 'float8' => (float) $value,
            'int2', 'int4' => (int) $value,
            'int8' => PHP_INT_SIZE >= 8 ? (int) $value : $value,
            default => $value,
        };
    }
}

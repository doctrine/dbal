<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;

use function array_merge;
use function array_values;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside {@see AbstractPlatform}.
 */
class SchemaCreateTableEventArgs extends SchemaEventArgs
{
    /** @var array<int, string> */
    private array $sql = [];

    /**
     * @param array<int, array<string, mixed>> $columns
     * @param array<string, mixed>             $options
     */
    public function __construct(
        private Table $table,
        private array $columns,
        private array $options,
        private AbstractPlatform $platform
    ) {
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->platform;
    }

    /**
     * @return $this
     */
    public function addSql(string ...$sql): self
    {
        $this->sql = array_merge($this->sql, array_values($sql));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSql(): array
    {
        return $this->sql;
    }
}

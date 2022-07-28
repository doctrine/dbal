<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Event Arguments used when the portable column definition is generated inside {@see AbstractPlatform}.
 */
class SchemaColumnDefinitionEventArgs extends SchemaEventArgs
{
    private ?Column $column = null;

    /**
     * @param array<string, mixed> $tableColumn
     */
    public function __construct(
        private readonly array $tableColumn,
        private readonly string $table,
        private readonly string $database,
        private readonly Connection $connection
    ) {
    }

    /**
     * Allows to clear the column which means the column will be excluded from
     * tables column list.
     *
     * @return $this
     */
    public function setColumn(?Column $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): ?Column
    {
        return $this->column;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTableColumn(): array
    {
        return $this->tableColumn;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}

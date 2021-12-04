<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;

/**
 * Event Arguments used when the portable index definition is generated inside {@see AbstractSchemaManager}.
 */
class SchemaIndexDefinitionEventArgs extends SchemaEventArgs
{
    private ?Index $index = null;

    /**
     * Raw index data as fetched from the database.
     *
     * @var array<string, mixed>
     */
    private array $tableIndex;

    private string $table;

    private Connection $connection;

    /**
     * @param array<string, mixed> $tableIndex
     */
    public function __construct(array $tableIndex, string $table, Connection $connection)
    {
        $this->tableIndex = $tableIndex;
        $this->table      = $table;
        $this->connection = $connection;
    }

    /**
     * Allows to clear the index which means the index will be excluded from tables index list.
     *
     * @return $this
     */
    public function setIndex(?Index $index): self
    {
        $this->index = $index;

        return $this;
    }

    public function getIndex(): ?Index
    {
        return $this->index;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTableIndex(): array
    {
        return $this->tableIndex;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}

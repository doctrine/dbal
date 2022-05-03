<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

/**
 * Event Arguments used when the portable column definition is generated inside {@see AbstractPlatform}.
 */
class SchemaColumnDefinitionEventArgs extends SchemaEventArgs
{
    /** @var Column|null */
    private $column;

    /**
     * Raw column data as fetched from the database.
     *
     * @var mixed[]
     */
    private $tableColumn;

    /** @var string */
    private $table;

    /** @var string */
    private $database;

    /** @var Connection */
    private $connection;

    /**
     * @param mixed[] $tableColumn
     * @param string  $table
     * @param string  $database
     */
    public function __construct(array $tableColumn, $table, $database, Connection $connection)
    {
        $this->tableColumn = $tableColumn;
        $this->table       = $table;
        $this->database    = $database;
        $this->connection  = $connection;
    }

    /**
     * Allows to clear the column which means the column will be excluded from
     * tables column list.
     */
    public function setColumn(?Column $column = null): SchemaColumnDefinitionEventArgs
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): ?Column
    {
        return $this->column;
    }

    /**
     * @return mixed[]
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

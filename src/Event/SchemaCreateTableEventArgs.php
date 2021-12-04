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
    private Table $table;

    /** @var array<int, array<string, mixed>> */
    private array $columns;

    /** @var array<string, mixed> */
    private array $options;

    private AbstractPlatform $platform;

    /** @var array<int, string> */
    private array $sql = [];

    /**
     * @param array<int, array<string, mixed>> $columns
     * @param array<string, mixed>             $options
     */
    public function __construct(Table $table, array $columns, array $options, AbstractPlatform $platform)
    {
        $this->table    = $table;
        $this->columns  = $columns;
        $this->options  = $options;
        $this->platform = $platform;
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

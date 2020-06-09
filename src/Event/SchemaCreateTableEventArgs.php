<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;

use function array_merge;

/**
 * Event Arguments used when SQL queries for creating tables are generated
 * inside Doctrine\DBAL\Platform\AbstractPlatform.
 */
class SchemaCreateTableEventArgs extends SchemaEventArgs
{
    /** @var Table */
    private $table;

    /** @var array<int, array<string, mixed>> */
    private $columns;

    /** @var array<string, mixed> */
    private $options;

    /** @var AbstractPlatform */
    private $platform;

    /** @var array<int, string> */
    private $sql = [];

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
        $this->sql = array_merge($this->sql, $sql);

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

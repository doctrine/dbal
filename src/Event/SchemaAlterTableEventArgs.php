<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\TableDiff;

use function array_merge;
use function array_values;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside {@see AbstractPlatform}.
 */
class SchemaAlterTableEventArgs extends SchemaEventArgs
{
    private TableDiff $tableDiff;

    private AbstractPlatform $platform;

    /** @var array<int, string> */
    private array $sql = [];

    public function __construct(TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->tableDiff = $tableDiff;
        $this->platform  = $platform;
    }

    public function getTableDiff(): TableDiff
    {
        return $this->tableDiff;
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

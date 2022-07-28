<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;

use function array_merge;
use function array_values;

/**
 * Event Arguments used when SQL queries for renaming table columns are generated inside {@see AbstractPlatform}.
 */
class SchemaAlterTableRenameColumnEventArgs extends SchemaEventArgs
{
    /** @var array<int, string> */
    private array $sql = [];

    public function __construct(
        private readonly string $oldColumnName,
        private readonly Column $column,
        private readonly TableDiff $tableDiff,
        private readonly AbstractPlatform $platform
    ) {
    }

    public function getOldColumnName(): string
    {
        return $this->oldColumnName;
    }

    public function getColumn(): Column
    {
        return $this->column;
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

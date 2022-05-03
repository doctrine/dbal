<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;

use function array_merge;
use function func_get_args;
use function is_array;

/**
 * Event Arguments used when SQL queries for renaming table columns are generated inside {@see AbstractPlatform}.
 */
class SchemaAlterTableRenameColumnEventArgs extends SchemaEventArgs
{
    /** @var string */
    private $oldColumnName;

    /** @var Column */
    private $column;

    /** @var TableDiff */
    private $tableDiff;

    /** @var AbstractPlatform */
    private $platform;

    /** @var string[] */
    private $sql = [];

    /**
     * @param string $oldColumnName
     */
    public function __construct($oldColumnName, Column $column, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->oldColumnName = $oldColumnName;
        $this->column        = $column;
        $this->tableDiff     = $tableDiff;
        $this->platform      = $platform;
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
     * Passing multiple SQL statements as an array is deprecated. Pass each statement as an individual argument instead.
     *
     * @param string|string[] $sql
     */
    public function addSql($sql): SchemaAlterTableRenameColumnEventArgs
    {
        $this->sql = array_merge($this->sql, is_array($sql) ? $sql : func_get_args());

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSql(): array
    {
        return $this->sql;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;

use function array_merge;

/**
 * Event Arguments used when SQL queries for adding table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 */
class SchemaAlterTableAddColumnEventArgs extends SchemaEventArgs
{
    /** @var Column */
    private $column;

    /** @var TableDiff */
    private $tableDiff;

    /** @var AbstractPlatform */
    private $platform;

    /** @var array<int, string> */
    private $sql = [];

    public function __construct(Column $column, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->column    = $column;
        $this->tableDiff = $tableDiff;
        $this->platform  = $platform;
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

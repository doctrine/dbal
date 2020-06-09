<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;

use function array_merge;

/**
 * Event Arguments used when SQL queries for changing table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 */
class SchemaAlterTableChangeColumnEventArgs extends SchemaEventArgs
{
    /** @var ColumnDiff */
    private $columnDiff;

    /** @var TableDiff */
    private $tableDiff;

    /** @var AbstractPlatform */
    private $platform;

    /** @var array<int, string> */
    private $sql = [];

    public function __construct(ColumnDiff $columnDiff, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->columnDiff = $columnDiff;
        $this->tableDiff  = $tableDiff;
        $this->platform   = $platform;
    }

    public function getColumnDiff(): ColumnDiff
    {
        return $this->columnDiff;
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

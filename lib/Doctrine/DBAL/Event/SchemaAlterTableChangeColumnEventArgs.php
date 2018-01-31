<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Event Arguments used when SQL queries for changing table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaAlterTableChangeColumnEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\ColumnDiff
     */
    private $columnDiff;

    /**
     * @var \Doctrine\DBAL\Schema\TableDiff
     */
    private $tableDiff;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    /**
     * @var array
     */
    private $sql = [];

    /**
     * @param \Doctrine\DBAL\Schema\ColumnDiff          $columnDiff
     * @param \Doctrine\DBAL\Schema\TableDiff           $tableDiff
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(ColumnDiff $columnDiff, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->columnDiff = $columnDiff;
        $this->tableDiff  = $tableDiff;
        $this->platform   = $platform;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ColumnDiff
     */
    public function getColumnDiff()
    {
        return $this->columnDiff;
    }

    /**
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    public function getTableDiff()
    {
        return $this->tableDiff;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @param string|array $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs
     */
    public function addSql($sql)
    {
        if (is_array($sql)) {
            $this->sql = array_merge($this->sql, $sql);
        } else {
            $this->sql[] = $sql;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getSql()
    {
        return $this->sql;
    }
}

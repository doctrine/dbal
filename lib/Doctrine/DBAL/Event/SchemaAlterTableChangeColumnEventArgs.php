<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;
use function array_merge;
use function is_array;

/**
 * Event Arguments used when SQL queries for changing table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 *
 * @link   www.doctrine-project.org
 */
class SchemaAlterTableChangeColumnEventArgs extends SchemaEventArgs
{
    /** @var ColumnDiff */
    private $_columnDiff;

    /** @var TableDiff */
    private $_tableDiff;

    /** @var AbstractPlatform */
    private $_platform;

    /** @var string[] */
    private $_sql = [];

    public function __construct(ColumnDiff $columnDiff, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->_columnDiff = $columnDiff;
        $this->_tableDiff  = $tableDiff;
        $this->_platform   = $platform;
    }

    /**
     * @return ColumnDiff
     */
    public function getColumnDiff()
    {
        return $this->_columnDiff;
    }

    /**
     * @return TableDiff
     */
    public function getTableDiff()
    {
        return $this->_tableDiff;
    }

    /**
     * @return AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * @param string|string[] $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaAlterTableChangeColumnEventArgs
     */
    public function addSql($sql)
    {
        if (is_array($sql)) {
            $this->_sql = array_merge($this->_sql, $sql);
        } else {
            $this->_sql[] = $sql;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSql()
    {
        return $this->_sql;
    }
}

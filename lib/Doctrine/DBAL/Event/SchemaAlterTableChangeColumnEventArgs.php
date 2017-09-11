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
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaAlterTableChangeColumnEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\ColumnDiff
     */
    private $_columnDiff;

    /**
     * @var \Doctrine\DBAL\Schema\TableDiff
     */
    private $_tableDiff;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $_platform;

    /**
     * @var array
     */
    private $_sql = [];

    /**
     * @param \Doctrine\DBAL\Schema\ColumnDiff          $columnDiff
     * @param \Doctrine\DBAL\Schema\TableDiff           $tableDiff
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(ColumnDiff $columnDiff, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->_columnDiff = $columnDiff;
        $this->_tableDiff  = $tableDiff;
        $this->_platform   = $platform;
    }

    /**
     * @return \Doctrine\DBAL\Schema\ColumnDiff
     */
    public function getColumnDiff()
    {
        return $this->_columnDiff;
    }

    /**
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    public function getTableDiff()
    {
        return $this->_tableDiff;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * @param string|array $sql
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
     * @return array
     */
    public function getSql()
    {
        return $this->_sql;
    }
}

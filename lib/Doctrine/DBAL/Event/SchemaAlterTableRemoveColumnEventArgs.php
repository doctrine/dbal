<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use function array_merge;
use function is_array;

/**
 * Event Arguments used when SQL queries for removing table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 *
 * @link   www.doctrine-project.org
 */
class SchemaAlterTableRemoveColumnEventArgs extends SchemaEventArgs
{
    /** @var Column */
    private $_column;

    /** @var TableDiff */
    private $_tableDiff;

    /** @var AbstractPlatform */
    private $_platform;

    /** @var array */
    private $_sql = [];

    public function __construct(Column $column, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->_column    = $column;
        $this->_tableDiff = $tableDiff;
        $this->_platform  = $platform;
    }

    /**
     * @return Column
     */
    public function getColumn()
    {
        return $this->_column;
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
     * @param string|array $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaAlterTableRemoveColumnEventArgs
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

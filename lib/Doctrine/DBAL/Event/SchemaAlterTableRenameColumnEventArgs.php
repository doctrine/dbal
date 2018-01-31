<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Event Arguments used when SQL queries for renaming table columns are generated inside Doctrine\DBAL\Platform\*Platform.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaAlterTableRenameColumnEventArgs extends SchemaEventArgs
{
    /**
     * @var string
     */
    private $oldColumnName;

    /**
     * @var \Doctrine\DBAL\Schema\Column
     */
    private $column;

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
     * @param string                                    $oldColumnName
     * @param \Doctrine\DBAL\Schema\Column              $column
     * @param \Doctrine\DBAL\Schema\TableDiff           $tableDiff
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct($oldColumnName, Column $column, TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->oldColumnName = $oldColumnName;
        $this->column        = $column;
        $this->tableDiff     = $tableDiff;
        $this->platform      = $platform;
    }

    /**
     * @return string
     */
    public function getOldColumnName()
    {
        return $this->oldColumnName;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getColumn()
    {
        return $this->column;
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
     * @return \Doctrine\DBAL\Event\SchemaAlterTableRenameColumnEventArgs
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

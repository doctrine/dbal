<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside Doctrine\DBAL\Platform\*Platform.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaAlterTableEventArgs extends SchemaEventArgs
{
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
     * @param \Doctrine\DBAL\Schema\TableDiff           $tableDiff
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(TableDiff $tableDiff, AbstractPlatform $platform)
    {
        $this->tableDiff = $tableDiff;
        $this->platform  = $platform;
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
     * @return \Doctrine\DBAL\Event\SchemaAlterTableEventArgs
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

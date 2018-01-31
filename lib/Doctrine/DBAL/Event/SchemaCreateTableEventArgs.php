<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaCreateTableEventArgs extends SchemaEventArgs
{
    /**
     * @var \Doctrine\DBAL\Schema\Table
     */
    private $table;

    /**
     * @var array
     */
    private $columns;

    /**
     * @var array
     */
    private $options;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    /**
     * @var array
     */
    private $sql = [];

    /**
     * @param \Doctrine\DBAL\Schema\Table               $table
     * @param array                                     $columns
     * @param array                                     $options
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(Table $table, array $columns, array $options, AbstractPlatform $platform)
    {
        $this->table    = $table;
        $this->columns  = $columns;
        $this->options  = $options;
        $this->platform = $platform;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
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
     * @return \Doctrine\DBAL\Event\SchemaCreateTableEventArgs
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

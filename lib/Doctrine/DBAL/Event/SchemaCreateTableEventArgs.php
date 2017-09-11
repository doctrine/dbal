<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use function array_merge;
use function is_array;

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
    private $_table;

    /**
     * @var array
     */
    private $_columns;

    /**
     * @var array
     */
    private $_options;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $_platform;

    /**
     * @var array
     */
    private $_sql = [];

    /**
     * @param \Doctrine\DBAL\Schema\Table               $table
     * @param array                                     $columns
     * @param array                                     $options
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     */
    public function __construct(Table $table, array $columns, array $options, AbstractPlatform $platform)
    {
        $this->_table    = $table;
        $this->_columns  = $columns;
        $this->_options  = $options;
        $this->_platform = $platform;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
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
     * @return \Doctrine\DBAL\Event\SchemaCreateTableEventArgs
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

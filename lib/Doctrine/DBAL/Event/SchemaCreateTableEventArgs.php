<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use function array_merge;
use function is_array;

/**
 * Event Arguments used when SQL queries for creating tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @link   www.doctrine-project.org
 */
class SchemaCreateTableEventArgs extends SchemaEventArgs
{
    /** @var Table */
    private $_table;

    /** @var Column[] */
    private $_columns;

    /** @var mixed[] */
    private $_options;

    /** @var AbstractPlatform */
    private $_platform;

    /** @var string[] */
    private $_sql = [];

    /**
     * @param Column[] $columns
     * @param mixed[]  $options
     */
    public function __construct(Table $table, array $columns, array $options, AbstractPlatform $platform)
    {
        $this->_table    = $table;
        $this->_columns  = $columns;
        $this->_options  = $options;
        $this->_platform = $platform;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * @return mixed[]
     */
    public function getOptions()
    {
        return $this->_options;
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
     * @return string[]
     */
    public function getSql()
    {
        return $this->_sql;
    }
}

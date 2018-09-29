<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use function is_string;

/**
 * Event Arguments used when the SQL query for dropping tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @link   www.doctrine-project.org
 */
class SchemaDropTableEventArgs extends SchemaEventArgs
{
    /** @var string|Table */
    private $_table;

    /** @var AbstractPlatform */
    private $_platform;

    /** @var string|null */
    private $_sql = null;

    /**
     * @param string|Table $table
     *
     * @throws InvalidArgumentException
     */
    public function __construct($table, AbstractPlatform $platform)
    {
        if (! $table instanceof Table && ! is_string($table)) {
            throw new InvalidArgumentException('SchemaDropTableEventArgs expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        $this->_table    = $table;
        $this->_platform = $platform;
    }

    /**
     * @return string|Table
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * @param string $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaDropTableEventArgs
     */
    public function setSql($sql)
    {
        $this->_sql = $sql;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSql()
    {
        return $this->_sql;
    }
}

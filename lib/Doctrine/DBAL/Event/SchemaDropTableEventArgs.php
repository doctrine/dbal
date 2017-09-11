<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use function is_string;

/**
 * Event Arguments used when the SQL query for dropping tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class SchemaDropTableEventArgs extends SchemaEventArgs
{
    /**
     * @var string|\Doctrine\DBAL\Schema\Table
     */
    private $_table;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $_platform;

    /**
     * @var string|null
     */
    private $_sql = null;

    /**
     * @param string|\Doctrine\DBAL\Schema\Table        $table
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($table, AbstractPlatform $platform)
    {
        if ( ! $table instanceof Table && !is_string($table)) {
            throw new \InvalidArgumentException('SchemaDropTableEventArgs expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        $this->_table    = $table;
        $this->_platform = $platform;
    }

    /**
     * @return string|\Doctrine\DBAL\Schema\Table
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
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

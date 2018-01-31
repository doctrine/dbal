<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;

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
    private $table;

    /**
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    /**
     * @var string|null
     */
    private $sql = null;

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

        $this->table    = $table;
        $this->platform = $platform;
    }

    /**
     * @return string|\Doctrine\DBAL\Schema\Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @param string $sql
     *
     * @return \Doctrine\DBAL\Event\SchemaDropTableEventArgs
     */
    public function setSql($sql)
    {
        $this->sql = $sql;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSql()
    {
        return $this->sql;
    }
}

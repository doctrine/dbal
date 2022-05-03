<?php

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;

/**
 * Event Arguments used when the SQL query for dropping tables are generated inside {@see AbstractPlatform}.
 */
class SchemaDropTableEventArgs extends SchemaEventArgs
{
    /** @var string|Table */
    private $table;

    /** @var AbstractPlatform */
    private $platform;

    /** @var string|null */
    private $sql;

    /**
     * @param string|Table $table
     *
     * @throws InvalidArgumentException
     */
    public function __construct($table, AbstractPlatform $platform)
    {
        $this->table    = $table;
        $this->platform = $platform;
    }

    /**
     * @return string|Table
     */
    public function getTable()
    {
        return $this->table;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->platform;
    }

    /**
     * @param string $sql
     */
    public function setSql($sql): SchemaDropTableEventArgs
    {
        $this->sql = $sql;

        return $this;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }
}

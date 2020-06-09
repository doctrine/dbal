<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;

/**
 * Event Arguments used when the SQL query for dropping tables are generated inside Doctrine\DBAL\Platform\AbstractPlatform.
 */
class SchemaDropTableEventArgs extends SchemaEventArgs
{
    /** @var string|Table */
    private $table;

    /** @var AbstractPlatform */
    private $platform;

    /** @var string|null */
    private $sql = null;

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
     * @return $this
     */
    public function setSql(string $sql): self
    {
        $this->sql = $sql;

        return $this;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }
}

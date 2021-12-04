<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Event;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Event Arguments used when the SQL query for dropping tables are generated inside {@see AbstractPlatform}.
 */
class SchemaDropTableEventArgs extends SchemaEventArgs
{
    private string $table;

    private AbstractPlatform $platform;

    private ?string $sql = null;

    public function __construct(string $table, AbstractPlatform $platform)
    {
        $this->table    = $table;
        $this->platform = $platform;
    }

    public function getTable(): string
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

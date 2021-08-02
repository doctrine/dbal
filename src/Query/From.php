<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/**
 * @internal
 */
final class From
{
    public string $table;

    public ?string $alias = null;

    public function __construct(string $table, ?string $alias = null)
    {
        $this->table = $table;
        $this->alias = $alias;
    }
}

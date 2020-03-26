<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/**
 * @internal
 */
final class From
{
    /** @var string */
    public $table;

    /** @var string|null */
    public $alias;

    public function __construct(string $table, ?string $alias = null)
    {
        $this->table = $table;
        $this->alias = $alias;
    }
}

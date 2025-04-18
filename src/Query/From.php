<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final readonly class From
{
    public function __construct(
        public string $table,
        public ?string $alias = null,
    ) {
    }
}

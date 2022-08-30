<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final class From
{
    public function __construct(
        public readonly string $table,
        public readonly ?string $alias = null,
    ) {
    }
}

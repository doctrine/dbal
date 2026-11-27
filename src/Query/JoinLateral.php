<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final class JoinLateral
{
    public function __construct(
        public readonly string|QueryBuilder $query,
        public readonly string $alias,
        public readonly ?string $conditions,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final class Union
{
    public function __construct(
        public readonly string|QueryBuilder $query,
        public readonly ?UnionType $type = null,
    ) {
    }
}

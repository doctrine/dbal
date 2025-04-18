<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final readonly class UnionQuery
{
    /**
     * @internal This class should be instantiated only by {@link QueryBuilder}.
     *
     * @param Union[]  $unionParts
     * @param string[] $orderBy
     */
    public function __construct(
        private array $unionParts,
        private array $orderBy,
        private Limit $limit,
    ) {
    }

    /** @return Union[] */
    public function getUnionParts(): array
    {
        return $this->unionParts;
    }

    /** @return string[] */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): Limit
    {
        return $this->limit;
    }
}

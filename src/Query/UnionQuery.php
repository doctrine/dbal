<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final class UnionQuery
{
    /**
     * @internal This class should be instantiated only by {@link QueryBuilder}.
     *
     * @param Union[]  $unionParts
     * @param string[] $orderBy
     */
    public function __construct(
        private readonly array $unionParts,
        private readonly array $orderBy,
        private readonly Limit $limit,
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

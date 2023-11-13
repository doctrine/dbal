<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final class SelectQuery
{
    /**
     * @internal This class should be instantiated only by {@link QueryBuilder}.
     *
     * @param string[] $columns
     * @param string[] $from
     * @param string[] $groupBy
     * @param string[] $orderBy
     */
    public function __construct(
        private readonly bool $distinct,
        private readonly array $columns,
        private readonly array $from,
        private readonly ?string $where,
        private readonly array $groupBy,
        private readonly ?string $having,
        private readonly array $orderBy,
        private readonly Limit $limit,
        private readonly ?ForUpdate $forUpdate,
    ) {
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    /** @return string[] */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return string[] */
    public function getFrom(): array
    {
        return $this->from;
    }

    public function getWhere(): ?string
    {
        return $this->where;
    }

    /** @return string[] */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getHaving(): ?string
    {
        return $this->having;
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

    public function getForUpdate(): ?ForUpdate
    {
        return $this->forUpdate;
    }
}

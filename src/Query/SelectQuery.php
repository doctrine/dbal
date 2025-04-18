<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final readonly class SelectQuery
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
        private bool $distinct,
        private array $columns,
        private array $from,
        private ?string $where,
        private array $groupBy,
        private ?string $having,
        private array $orderBy,
        private Limit $limit,
        private ?ForUpdate $forUpdate,
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

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final class Limit
{
    public function __construct(
        private readonly ?int $maxResults,
        private readonly int $firstResult,
    ) {
    }

    public function isDefined(): bool
    {
        return $this->maxResults !== null || $this->firstResult !== 0;
    }

    public function getMaxResults(): ?int
    {
        return $this->maxResults;
    }

    public function getFirstResult(): int
    {
        return $this->firstResult;
    }
}

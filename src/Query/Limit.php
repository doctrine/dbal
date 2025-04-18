<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final readonly class Limit
{
    public function __construct(
        private ?int $maxResults,
        private int $firstResult,
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

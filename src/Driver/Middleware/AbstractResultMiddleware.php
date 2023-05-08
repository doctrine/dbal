<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;

abstract class AbstractResultMiddleware implements Result
{
    public function __construct(private readonly Result $wrappedResult)
    {
    }

    public function fetchNumeric(): array|false
    {
        return $this->wrappedResult->fetchNumeric();
    }

    public function fetchAssociative(): array|false
    {
        return $this->wrappedResult->fetchAssociative();
    }

    public function fetchOne(): mixed
    {
        return $this->wrappedResult->fetchOne();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->wrappedResult->fetchAllNumeric();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->wrappedResult->fetchAllAssociative();
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->wrappedResult->fetchFirstColumn();
    }

    public function rowCount(): int|string
    {
        return $this->wrappedResult->rowCount();
    }

    public function columnCount(): int
    {
        return $this->wrappedResult->columnCount();
    }

    public function free(): void
    {
        $this->wrappedResult->free();
    }
}

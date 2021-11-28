<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;

abstract class AbstractResultMiddleware implements Result
{
    private Result $wrappedResult;

    public function __construct(Result $result)
    {
        $this->wrappedResult = $result;
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
     * {@inheritdoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->wrappedResult->fetchAllNumeric();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->wrappedResult->fetchAllAssociative();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->wrappedResult->fetchFirstColumn();
    }

    public function rowCount(): int
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

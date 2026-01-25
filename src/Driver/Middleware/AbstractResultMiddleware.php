<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Override;

abstract class AbstractResultMiddleware implements Result
{
    public function __construct(private readonly Result $wrappedResult)
    {
    }

    #[Override]
    public function fetchNumeric(): array|false
    {
        return $this->wrappedResult->fetchNumeric();
    }

    #[Override]
    public function fetchAssociative(): array|false
    {
        return $this->wrappedResult->fetchAssociative();
    }

    #[Override]
    public function fetchOne(): mixed
    {
        return $this->wrappedResult->fetchOne();
    }

    #[Override]
    public function fetchAllNumeric(): array
    {
        return $this->wrappedResult->fetchAllNumeric();
    }

    #[Override]
    public function fetchAllAssociative(): array
    {
        return $this->wrappedResult->fetchAllAssociative();
    }

    #[Override]
    public function fetchFirstColumn(): array
    {
        return $this->wrappedResult->fetchFirstColumn();
    }

    #[Override]
    public function rowCount(): int|string
    {
        return $this->wrappedResult->rowCount();
    }

    #[Override]
    public function columnCount(): int
    {
        return $this->wrappedResult->columnCount();
    }

    #[Override]
    public function getColumnName(int $index): string
    {
        return $this->wrappedResult->getColumnName($index);
    }

    #[Override]
    public function free(): void
    {
        $this->wrappedResult->free();
    }
}

<?php

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;

abstract class AbstractResultMiddleware implements Result
{
    /** @var Result */
    private $wrappedResult;

    public function __construct(Result $result)
    {
        $this->wrappedResult = $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        return $this->wrappedResult->fetchNumeric();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->wrappedResult->fetchAssociative();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
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

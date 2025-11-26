<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Async;

use Doctrine\DBAL\Types\Type;

/**
 * Represents an asynchronous query with its SQL, parameters, and parameter types.
 *
 * This is a value object used to batch multiple queries for parallel execution.
 */
final class AsyncQuery
{
    /** @var string */
    private string $sql;

    /** @var list<mixed>|array<string, mixed> */
    private array $params;

    /** @var array<int, int|string|Type|null>|array<string, int|string|Type|null> */
    private array $types;

    /**
     * @param string                                                               $sql    The SQL query string
     * @param list<mixed>|array<string, mixed>                                     $params Query parameters
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     */
    public function __construct(string $sql, array $params = [], array $types = [])
    {
        $this->sql    = $sql;
        $this->params = $params;
        $this->types  = $types;
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    /**
     * @return list<mixed>|array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}


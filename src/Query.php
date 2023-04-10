<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * An SQL query together with its bound parameters.
 *
 * @psalm-immutable
 */
final class Query
{
    /**
     * The SQL query.
     */
    private string $sql;

    /**
     * The parameters bound to the query.
     *
     * @var array<mixed>
     */
    private array $params;

    /**
     * The types of the parameters bound to the query.
     *
     * @var array<Type|int|string|null>
     * @psalm-var array<int|string, int|ParameterType::*|Types::*|Type|null>
     */
    private array $types;

    /**
     * @param array<mixed>                $params
     * @param array<Type|int|string|null> $types
     * @psalm-param array<int|string, int|ParameterType::*|Types::*|Type|null> $types
     */
    public function __construct(string $sql, array $params, array $types)
    {
        $this->sql    = $sql;
        $this->params = $params;
        $this->types  = $types;
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    /** @return array<mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array<Type|int|string|null>
     * @psalm-return array<int|string, int|ParameterType::*|Types::*|Type|null>
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}

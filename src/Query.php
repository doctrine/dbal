<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Types\Type;

/**
 * An SQL query together with its bound parameters.
 *
 * @psalm-immutable
 */
final class Query
{
    /**
     * @param array<mixed>                         $params
     * @param array<int|string|ParameterType|Type> $types
     *
     * @psalm-suppress ImpurePropertyAssignment
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $params,
        private readonly array $types,
    ) {
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

    /** @return array<int|string|ParameterType|Type> */
    public function getTypes(): array
    {
        return $this->types;
    }
}

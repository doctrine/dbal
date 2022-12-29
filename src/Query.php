<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * An SQL query together with its bound parameters.
 *
 * @psalm-immutable
 * @psalm-import-type WrapperParameterType from Connection
 */
final class Query
{
    /**
     * @param array<mixed> $params
     * @psalm-param array<WrapperParameterType> $types
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

    /** @psalm-return array<WrapperParameterType> */
    public function getTypes(): array
    {
        return $this->types;
    }
}

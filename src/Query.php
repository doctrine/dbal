<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * An SQL query together with its bound parameters.
 *
 * @phpstan-import-type WrapperParameterType from Connection
 */
final class Query
{
    /**
     * @param array<mixed> $params
     * @phpstan-param array<WrapperParameterType> $types
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

    /** @phpstan-return array<WrapperParameterType> */
    public function getTypes(): array
    {
        return $this->types;
    }
}

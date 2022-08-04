<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;

abstract class AbstractStatementMiddleware implements Statement
{
    public function __construct(private readonly Statement $wrappedStatement)
    {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->wrappedStatement->bindValue($param, $value, $type);
    }

    /**
     * @deprecated Use {@see bindValue()} instead.
     */
    public function bindParam(
        int|string $param,
        mixed &$variable,
        ParameterType $type,
        ?int $length = null
    ): void {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__
        );

        $this->wrappedStatement->bindParam($param, $variable, $type, $length);
    }

    public function execute(): Result
    {
        return $this->wrappedStatement->execute();
    }
}

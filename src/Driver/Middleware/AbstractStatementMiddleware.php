<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Override;

abstract class AbstractStatementMiddleware implements Statement
{
    public function __construct(private readonly Statement $wrappedStatement)
    {
    }

    #[Override]
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->wrappedStatement->bindValue($param, $value, $type);
    }

    #[Override]
    public function execute(): Result
    {
        return $this->wrappedStatement->execute();
    }
}

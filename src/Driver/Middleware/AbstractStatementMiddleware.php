<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

abstract class AbstractStatementMiddleware implements Statement
{
    private Statement $wrappedStatement;

    public function __construct(Statement $wrappedStatement)
    {
        $this->wrappedStatement = $wrappedStatement;
    }

    public function bindValue(int|string $param, mixed $value, int $type = ParameterType::STRING): void
    {
        $this->wrappedStatement->bindValue($param, $value, $type);
    }

    public function bindParam(
        int|string $param,
        mixed &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        $this->wrappedStatement->bindParam($param, $variable, $type, $length);
    }

    public function execute(?array $params = null): Result
    {
        return $this->wrappedStatement->execute($params);
    }
}

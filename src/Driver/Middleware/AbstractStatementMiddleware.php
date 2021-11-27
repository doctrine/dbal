<?php

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

abstract class AbstractStatementMiddleware implements Statement
{
    /** @var Statement */
    private $wrappedStatement;

    public function __construct(Statement $wrappedStatement)
    {
        $this->wrappedStatement = $wrappedStatement;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->wrappedStatement->bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        return $this->wrappedStatement->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): Result
    {
        return $this->wrappedStatement->execute($params);
    }
}

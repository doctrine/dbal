<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class Statement extends AbstractStatementMiddleware
{
    private LoggerInterface $logger;

    private string $sql;

    /** @var array<int,mixed>|array<string,mixed> */
    private array $params = [];

    /** @var array<int,int>|array<string,int> */
    private array $types = [];

    /**
     * @internal This statement can be only instantiated by its connection.
     */
    public function __construct(StatementInterface $statement, LoggerInterface $logger, string $sql)
    {
        parent::__construct($statement);

        $this->logger = $logger;
        $this->sql    = $sql;
    }

    public function bindParam(
        int|string $param,
        mixed &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        $this->params[$param] = &$variable;
        $this->types[$param]  = $type;

        parent::bindParam($param, $variable, $type, $length);
    }

    public function bindValue(int|string $param, mixed $value, int $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        $this->types[$param]  = $type;

        parent::bindValue($param, $value, $type);
    }

    public function execute(?array $params = null): ResultInterface
    {
        $this->logger->debug('Executing statement: {sql} (parameters: {params}, types: {types})', [
            'sql'    => $this->sql,
            'params' => $params ?? $this->params,
            'types'  => $this->types,
        ]);

        return parent::execute($params);
    }
}

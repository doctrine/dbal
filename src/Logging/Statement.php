<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

final class Statement implements StatementInterface
{
    private StatementInterface $statement;

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
        $this->statement = $statement;
        $this->logger    = $logger;
        $this->sql       = $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null): void
    {
        $this->params[$param] = &$variable;
        $this->types[$param]  = $type;

        $this->statement->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        $this->types[$param]  = $type;

        $this->statement->bindValue($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): ResultInterface
    {
        $this->logger->debug('Executing statement: {sql} (parameters: {params}, types: {types})', [
            'sql'    => $this->sql,
            'params' => $params ?? $this->params,
            'types'  => $this->types,
        ]);

        return $this->statement->execute($params);
    }
}

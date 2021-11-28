<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;

use function array_slice;
use function func_get_args;

final class Statement extends AbstractStatementMiddleware
{
    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $sql;

    /** @var array<int,mixed>|array<string,mixed> */
    private $params = [];

    /** @var array<int,int>|array<string,int> */
    private $types = [];

    /**
     * @internal This statement can be only instantiated by its connection.
     */
    public function __construct(StatementInterface $statement, LoggerInterface $logger, string $sql)
    {
        parent::__construct($statement);

        $this->logger = $logger;
        $this->sql    = $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $this->params[$param] = &$variable;
        $this->types[$param]  = $type;

        return parent::bindParam($param, $variable, $type, ...array_slice(func_get_args(), 3));
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $this->params[$param] = $value;
        $this->types[$param]  = $type;

        return parent::bindValue($param, $value, $type);
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

        return parent::execute($params);
    }
}

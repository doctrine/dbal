<?php

namespace Doctrine\DBAL\Driver\PDO\SQLSrv;

use Doctrine\DBAL\Driver\Exception\UnknownParameterType;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;

use function fseek;
use function ftell;
use function func_num_args;
use function is_resource;
use function stream_get_meta_data;

use const SEEK_SET;

final class Statement extends AbstractStatementMiddleware
{
    private PDOStatement $statement;

    /** @var mixed[]|null */
    private ?array $paramResources = null;

    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(PDOStatement $statement)
    {
        parent::__construct($statement);

        $this->statement = $statement;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@see bindValue()} instead.
     *
     * @param string|int $param
     * @param mixed      $variable
     * @param int        $type
     * @param int|null   $length
     * @param mixed      $driverOptions The usage of the argument is deprecated.
     *
     * @throws UnknownParameterType
     *
     * @psalm-assert ParameterType::* $type
     */
    public function bindParam(
        $param,
        &$variable,
        $type = ParameterType::STRING,
        $length = null,
        $driverOptions = null
    ): bool {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5563',
            '%s is deprecated. Use bindValue() instead.',
            __METHOD__,
        );

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                    . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        if (func_num_args() > 4) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4533',
                'The $driverOptions argument of Statement::bindParam() is deprecated.',
            );
        }

        switch ($type) {
            case ParameterType::LARGE_OBJECT:
            case ParameterType::BINARY:
                $driverOptions ??= PDO::SQLSRV_ENCODING_BINARY;

                break;

            case ParameterType::ASCII:
                $type          = ParameterType::STRING;
                $length        = 0;
                $driverOptions = PDO::SQLSRV_ENCODING_SYSTEM;
                break;
        }

        return $this->statement->bindParam($param, $variable, $type, $length ?? 0, $driverOptions);
    }

    /**
     * @throws UnknownParameterType
     *
     * {@inheritDoc}
     *
     * @psalm-assert ParameterType::* $type
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindValue() is deprecated.'
                    . ' Pass the type corresponding to the parameter being bound.',
            );
        }

        if ($type === ParameterType::LARGE_OBJECT || $type === ParameterType::BINARY) {
            $this->trackParamResource($value);
        }

        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($params = null): Result
    {
        $resourceOffsets = $this->getResourceOffsets();
        try {
            return parent::execute($params);
        } finally {
            if ($resourceOffsets !== null) {
                $this->restoreResourceOffsets($resourceOffsets);
            }
        }
    }

    /**
     * Track a binary parameter reference at binding time. These
     * are cached for later analysis by the getResourceOffsets.
     *
     * @param mixed $resource
     */
    private function trackParamResource($resource): void
    {
        if (! is_resource($resource)) {
            return;
        }

        $this->paramResources ??= [];
        $this->paramResources[] = $resource;
    }

    /**
     * Determine the offset that any resource parameters needs to be
     * restored to after the statement is executed. Call immediately
     * before execute (not during bindValue) to get the most accurate offset.
     *
     * @return int[]|null Return offsets to restore if needed. The array may be sparse.
     */
    private function getResourceOffsets(): ?array
    {
        if ($this->paramResources === null) {
            return null;
        }

        $resourceOffsets = null;
        foreach ($this->paramResources as $index => $resource) {
            $position = false;
            if (stream_get_meta_data($resource)['seekable']) {
                $position = ftell($resource);
            }

            if ($position === false) {
                continue;
            }

            $resourceOffsets       ??= [];
            $resourceOffsets[$index] = $position;
        }

        if ($resourceOffsets === null) {
            $this->paramResources = null;
        }

        return $resourceOffsets;
    }

    /**
     * Restore resource offsets moved by PDOStatement->execute
     *
     * @param int[]|null $resourceOffsets The offsets returned by getResourceOffsets.
     */
    private function restoreResourceOffsets(?array $resourceOffsets): void
    {
        if ($resourceOffsets === null || $this->paramResources === null) {
            return;
        }

        foreach ($resourceOffsets as $index => $offset) {
            fseek($this->paramResources[$index], $offset, SEEK_SET);
        }

        $this->paramResources = null;
    }
}

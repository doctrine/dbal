<?php

namespace Doctrine\DBAL\Driver\SQLite3;

use Doctrine\DBAL\Driver\Exception\UnknownParameterType;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use SQLite3;
use SQLite3Stmt;

use function assert;
use function fseek;
use function ftell;
use function func_num_args;
use function is_int;
use function is_resource;
use function stream_get_meta_data;

use const SEEK_SET;
use const SQLITE3_BLOB;
use const SQLITE3_INTEGER;
use const SQLITE3_NULL;
use const SQLITE3_TEXT;

final class Statement implements StatementInterface
{
    private const PARAM_TYPE_MAP = [
        ParameterType::NULL => SQLITE3_NULL,
        ParameterType::INTEGER => SQLITE3_INTEGER,
        ParameterType::STRING => SQLITE3_TEXT,
        ParameterType::ASCII => SQLITE3_TEXT,
        ParameterType::BINARY => SQLITE3_BLOB,
        ParameterType::LARGE_OBJECT => SQLITE3_BLOB,
        ParameterType::BOOLEAN => SQLITE3_INTEGER,
    ];

    private SQLite3 $connection;
    private SQLite3Stmt $statement;

    /** @var mixed[]|null */
    private ?array $paramResources = null;

    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(SQLite3 $connection, SQLite3Stmt $statement)
    {
        $this->connection = $connection;
        $this->statement  = $statement;
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

        $sqliteType = $this->convertParamType($type);
        if ($sqliteType === SQLITE3_BLOB) {
            $this->trackParamResource($value);
        }

        return $this->statement->bindValue($param, $value, $sqliteType);
    }

    /**
     * @throws UnknownParameterType
     *
     * {@inheritDoc}
     *
     * @psalm-assert ParameterType::* $type
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
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

        return $this->statement->bindParam($param, $variable, $this->convertParamType($type));
    }

    /** @inheritDoc */
    public function execute($params = null): Result
    {
        if ($params !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5556',
                'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                . ' Statement::bindParam() or Statement::bindValue() instead.',
            );

            foreach ($params as $param => $value) {
                if (is_int($param)) {
                    $this->bindValue($param + 1, $value, ParameterType::STRING);
                } else {
                    $this->bindValue($param, $value, ParameterType::STRING);
                }
            }
        }

        $resourceOffsets = $this->getResourceOffsets();
        try {
            $result = $this->statement->execute();
        } catch (\Exception $e) {
            throw Exception::new($e);
        } finally {
            if ($resourceOffsets !== null) {
                $this->restoreResourceOffsets($resourceOffsets);
            }
        }

        assert($result !== false);

        return new Result($result, $this->connection->changes());
    }

    /**
     * @psalm-return value-of<self::PARAM_TYPE_MAP>
     *
     * @psalm-assert ParameterType::* $type
     */
    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            throw UnknownParameterType::new($type);
        }

        return self::PARAM_TYPE_MAP[$type];
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

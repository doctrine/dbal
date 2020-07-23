<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;

/**
 * Portability wrapper for a Connection.
 */
final class Connection implements ConnectionInterface
{
    public const PORTABILITY_ALL           = 255;
    public const PORTABILITY_NONE          = 0;
    public const PORTABILITY_RTRIM         = 1;
    public const PORTABILITY_EMPTY_TO_NULL = 4;
    public const PORTABILITY_FIX_CASE      = 8;

    /** @var ConnectionInterface */
    private $connection;

    /** @var Converter */
    private $converter;

    public function __construct(ConnectionInterface $connection, Converter $converter)
    {
        $this->connection = $connection;
        $this->converter  = $converter;
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            $this->connection->prepare($sql),
            $this->converter
        );
    }

    public function query(string $sql): DriverResult
    {
        return new Result(
            $this->connection->query($sql),
            $this->converter
        );
    }

    public function quote(string $input): string
    {
        return $this->connection->quote($input);
    }

    public function exec(string $sql): int
    {
        return $this->connection->exec($sql);
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }
}

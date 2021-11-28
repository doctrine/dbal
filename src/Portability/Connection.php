<?php

namespace Doctrine\DBAL\Portability;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use LogicException;

/**
 * Portability wrapper for a Connection.
 */
final class Connection implements ServerInfoAwareConnection
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

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    public function exec(string $sql): int
    {
        return $this->connection->exec($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($name !== null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/issues/4687',
                'The usage of Connection::lastInsertId() with a sequence name is deprecated.'
            );
        }

        return $this->connection->lastInsertId($name);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    /**
     * {@inheritDoc}
     */
    public function getServerVersion()
    {
        if (! $this->connection instanceof ServerInfoAwareConnection) {
            throw new LogicException('The underlying connection is not a ServerInfoAwareConnection');
        }

        return $this->connection->getServerVersion();
    }
}

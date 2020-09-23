<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Abstraction\Result as ResultInterface;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Traversable;

final class Result implements ResultInterface
{
    /** @var DriverResult */
    private $result;

    /** @var Connection */
    private $connection;

    /**
     * @internal The result can be only instantiated by {@link Connection} or {@link Statement}.
     */
    public function __construct(DriverResult $result, Connection $connection)
    {
        $this->result     = $result;
        $this->connection = $connection;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchNumeric()
    {
        try {
            return $this->result->fetchNumeric();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchAssociative()
    {
        try {
            return $this->result->fetchAssociative();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchOne()
    {
        try {
            return $this->result->fetchOne();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchAllNumeric(): array
    {
        try {
            return $this->result->fetchAllNumeric();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchAllAssociative(): array
    {
        try {
            return $this->result->fetchAllAssociative();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function fetchFirstColumn(): array
    {
        try {
            return $this->result->fetchFirstColumn();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @return Traversable<int,array<int,mixed>>
     *
     * @throws Exception
     */
    public function iterateNumeric(): Traversable
    {
        try {
            while (($row = $this->result->fetchNumeric()) !== false) {
                yield $row;
            }
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function iterateAssociative(): Traversable
    {
        try {
            while (($row = $this->result->fetchAssociative()) !== false) {
                yield $row;
            }
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @return Traversable<int,mixed>
     *
     * @throws Exception
     */
    public function iterateColumn(): Traversable
    {
        try {
            while (($value = $this->result->fetchOne()) !== false) {
                yield $value;
            }
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @throws Exception
     */
    public function rowCount(): int
    {
        try {
            return $this->result->rowCount();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    /**
     * @throws Exception
     */
    public function columnCount(): int
    {
        try {
            return $this->result->columnCount();
        } catch (DriverException $e) {
            throw $this->connection->convertException($e);
        }
    }

    public function free(): void
    {
        $this->result->free();
    }
}

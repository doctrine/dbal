<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\PDOException;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use PDO;
use PDOStatement;

use function assert;
use function is_array;

final class Result implements ResultInterface
{
    /** @var PDOStatement */
    private $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchNumeric()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchOne()
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->fetchAll(PDO::FETCH_COLUMN);
    }

    public function rowCount(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function columnCount(): int
    {
        try {
            return $this->statement->columnCount();
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    public function free(): void
    {
        try {
            $this->statement->closeCursor();
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @return mixed|false
     *
     * @throws PDOException
     */
    private function fetch(int $mode)
    {
        try {
            return $this->statement->fetch($mode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @return array<int,mixed>
     *
     * @throws PDOException
     */
    private function fetchAll(int $mode): array
    {
        try {
            $data = $this->statement->fetchAll($mode);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        assert(is_array($data));

        return $data;
    }
}

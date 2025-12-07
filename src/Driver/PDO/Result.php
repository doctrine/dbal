<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use PDO;
use PDOException;
use PDOStatement;
use ValueError;

use function assert;
use function is_array;

final class Result implements ResultInterface
{
    /** @internal The result can be only instantiated by its driver connection or statement. */
    public function __construct(private readonly PDOStatement $statement)
    {
    }

    public function fetchNumeric(): array|false
    {
        /** @var list<mixed> $result */
        $result = $this->fetch(PDO::FETCH_NUM);

        if ($result === false) {
            return false;
        }

        assert(is_array($result));

        return $result;
    }

    public function fetchAssociative(): array|false
    {
        /** @var array<string, mixed> $result */
        $result = $this->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return false;
        }

        assert(is_array($result));

        return $result;
    }

    public function fetchOne(): mixed
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllNumeric(): array
    {
        /** @var list<list<mixed>> $result */
        $result = $this->fetchAll(PDO::FETCH_NUM);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAllAssociative(): array
    {
        /** @var list<array<string, mixed>> $result */
        $result = $this->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchFirstColumn(): array
    {
        /** @var list<mixed> $result */
        $result = $this->fetchAll(PDO::FETCH_COLUMN);

        return $result;
    }

    public function rowCount(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    public function columnCount(): int
    {
        try {
            return $this->statement->columnCount();
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /** @throws Exception */
    public function getColumnName(int $index): string
    {
        try {
            $meta = $this->statement->getColumnMeta($index);
        } catch (ValueError $exception) {
            throw InvalidColumnIndex::new($index, $exception);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        if ($meta === false) {
            throw InvalidColumnIndex::new($index);
        }

        return $meta['name'];
    }

    public function free(): void
    {
        $this->statement->closeCursor();
    }

    /**
     * @phpstan-param PDO::FETCH_* $mode
     *
     * @throws Exception
     */
    private function fetch(int $mode): mixed
    {
        try {
            return $this->statement->fetch($mode);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * @phpstan-param PDO::FETCH_* $mode
     *
     * @return list<mixed>
     *
     * @throws Exception
     */
    private function fetchAll(int $mode): array
    {
        try {
            return $this->statement->fetchAll($mode);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }
}

<?php

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;

use function array_values;
use function count;
use function reset;

/**
 * @deprecated
 */
class ArrayStatement implements ResultStatement, Result
{
    /** @var mixed[] */
    private $data;

    /** @var int */
    private $columnCount = 0;

    /** @var int */
    private $num = 0;

    /**
     * @param mixed[] $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        if (count($data) === 0) {
            return;
        }

        $this->columnCount = count($data[0]);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use free() instead.
     */
    public function closeCursor()
    {
        $this->free();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->columnCount;
    }

    public function rowCount(): int
    {
        if ($this->data === null) {
            return 0;
        }

        return count($this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return array_values($row);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        $row = $this->fetch();

        if ($row === false) {
            return false;
        }

        return reset($row);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric(): array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
    {
        return FetchUtils::fetchFirstColumn($this);
    }

    public function free(): void
    {
        $this->data = [];
    }

    /**
     * @return mixed|false
     */
    private function fetch()
    {
        if (! isset($this->data[$this->num])) {
            return false;
        }

        return $this->data[$this->num++];
    }
}

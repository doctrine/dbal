<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Cache;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\ResultStatement;
use function array_values;
use function count;
use function reset;

final class ArrayStatement implements ResultStatement
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

    public function closeCursor() : void
    {
        $this->data = [];
    }

    public function columnCount() : int
    {
        return $this->columnCount;
    }

    public function rowCount() : int
    {
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
    public function fetchAllNumeric() : array
    {
        return FetchUtils::fetchAllNumeric($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return FetchUtils::fetchAllAssociative($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return FetchUtils::fetchColumn($this);
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

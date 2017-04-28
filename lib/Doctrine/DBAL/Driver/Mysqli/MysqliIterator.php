<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractIterator;

class MysqliIterator extends AbstractIterator implements \Iterator
{
    protected function fetch()
    {
        switch ($this->defaultFetchMode) {
            case \PDO::FETCH_COLUMN:
                return $this->fetchColumn();
            default:
                return $this->fetch($this->defaultFetchMode);
        }
    }
}

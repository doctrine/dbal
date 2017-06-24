<?php

namespace Doctrine\DBAL\Driver;

class PDOStatementIterator implements \Iterator
{
    public $stmt;
    public $cache;
    public $position = 0;

    public function __construct(\PDOStatement $stmt)
    {
        $this->cache = [];
        $this->position = 0;
        $this->stmt = $stmt;
        $this->next();
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->cache[$this->position]);
    }

    public function current()
    {
        return $this->cache[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        if ($this->valid()) {
            $this->cache[$this->position];
            $this->position++;
        } else {
            $this->cache[$this->position] = $this->stmt->fetch();
            $this->position++;
        }

    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->stmt, $name], $arguments);
    }
}

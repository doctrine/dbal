<?php

namespace Doctrine\DBAL\Driver;

abstract class AbstractIterator
{
    protected $cursor;
    protected $key;
    protected $current;
    protected $fetched = 0;
    protected $defaultFetchMode;

    public function __construct($cursor, $defaultFetchMode = \PDO::FETCH_BOTH)
    {
        $this->cursor = $cursor;
        $this->defaultFetchMode = $defaultFetchMode;
        $this->next();
    }

    public function current()
    {
        return $this->current;
    }

    public function next()
    {
        $this->key = $this->fetched;
        $this->current = $this->fetch();

        if ($this->current) {
            $this->fetched++;
        }
    }

    public function key()
    {
        return $this->key;
    }

    public function valid()
    {
        return (bool)$this->current;
    }

    public function rewind()
    {
        // Not supported
    }

    abstract protected function fetch();
}

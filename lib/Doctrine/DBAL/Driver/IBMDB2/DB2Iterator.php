<?php

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\AbstractIterator;

class DB2Iterator extends AbstractIterator implements \Iterator
{
    protected function fetch()
    {
        switch ($this->defaultFetchMode) {
            case \PDO::FETCH_BOTH:
                return db2_fetch_both($this->cursor);
            case \PDO::FETCH_ASSOC:
                return db2_fetch_assoc($this->cursor);
            case \PDO::FETCH_CLASS:
                return db2_fetch_object($this->cursor);
            case \PDO::FETCH_NUM:
                return db2_fetch_array($this->cursor);
            case \PDO::FETCH_OBJ:
                return db2_fetch_object($this->cursor);
            default:
                throw new DB2Exception("Given Fetch-Style " . $this->defaultFetchMode . " is not supported.");
        }
    }
}

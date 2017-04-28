<?php

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\AbstractIterator;

class SQLSrvIterator extends AbstractIterator implements \Iterator
{
    protected function fetch()
    {
        return sqlsrv_fetch_array($this->cursor, $this->defaultFetchMode);
    }
}

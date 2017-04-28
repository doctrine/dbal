<?php

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\AbstractIterator;

class SQLAnywhereIterator extends AbstractIterator implements \Iterator
{
    protected function fetch()
    {
        $result = sasql_stmt_result_metadata($this->cursor);

        switch ($this->defaultFetchMode) {
            case \PDO::FETCH_ASSOC:
                return sasql_fetch_assoc($this->cursor);
            case \PDO::FETCH_BOTH:
                return sasql_fetch_array($result, SASQL_BOTH);
            case \PDO::FETCH_CLASS:
                return sasql_fetch_object($result);
            case \PDO::FETCH_NUM:
                return sasql_fetch_row($result);
            case \PDO::FETCH_OBJ:
                return sasql_fetch_object($result);
            default:
                throw new SQLAnywhereException('Fetch mode is not supported: ' . $this->defaultFetchMode);
        }
    }
}

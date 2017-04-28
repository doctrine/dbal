<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\AbstractIterator;

class OCI8Iterator extends AbstractIterator implements \Iterator
{
    private static $fetchModeMap = array(
        \PDO::FETCH_BOTH => OCI_BOTH,
        \PDO::FETCH_ASSOC => OCI_ASSOC,
        \PDO::FETCH_NUM => OCI_NUM,
        \PDO::FETCH_COLUMN => OCI_NUM,
    );

    protected function fetch()
    {
        $result = [];

        if (\PDO::FETCH_OBJ === $this->defaultFetchMode) {
            return oci_fetch_object($this->cursor);
        }

        if (self::$fetchModeMap[$this->defaultFetchMode] === OCI_BOTH) {
            return oci_fetch_array(
                $this->cursor,
                $this->defaultFetchMode | OCI_RETURN_NULLS | OCI_RETURN_LOBS
            );
        }

        $fetchStructure = OCI_FETCHSTATEMENT_BY_ROW;
        if ($this->defaultFetchMode === \PDO::FETCH_COLUMN) {
            $fetchStructure = OCI_FETCHSTATEMENT_BY_COLUMN;
        }

        oci_fetch_all(
            $this->cursor,
            $result,
            0,
            -1,
            self::$fetchModeMap[$this->defaultFetchMode] | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS
        );

        return $result;
    }
}

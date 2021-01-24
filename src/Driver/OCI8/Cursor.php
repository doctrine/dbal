<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Exception\Error;

class Cursor extends Statement
{
    /**
     * @param resource $dbh The connection resource
     */
    public function __construct($dbh, ExecutionMode $executionMode)
    {
        parent::__construct($dbh, $query = '', $executionMode);

        $stmt = oci_new_cursor($dbh);
        if ($stmt === false) {
            throw Error::new($dbh);
        }

        $this->_sth = $stmt;
    }

    /**
     * @return resource
     */
    public function getStatement()
    {
        return $this->_sth;
    }
}

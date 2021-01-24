<?php

namespace Doctrine\DBAL\Driver\OCI8;

class Cursor extends Statement
{
    public function __construct($dbh, ExecutionMode $executionMode, $sth = null)
    {
        $this->_dbh          = $dbh;
        $this->executionMode = $executionMode;
        $this->_sth          = $sth ?: oci_new_cursor($dbh);
    }

    public function getStatement()
    {
        return $this->_sth;
    }
}
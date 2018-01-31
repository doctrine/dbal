<?php

namespace Doctrine\Tests\Mocks;

class ConnectionMock extends \Doctrine\DBAL\Connection
{
    private $fetchOneResult;
    private $platformMock;
    private $lastInsertId = 0;
    private $inserts = array();

    public function __construct(array $params, $driver, $config = null, $eventManager = null)
    {
        $this->platformMock = new DatabasePlatformMock();

        parent::__construct($params, $driver, $config, $eventManager);

        // Override possible assignment of platform to database platform mock
        $this->platform = $this->platformMock;
    }

    /**
     * @override
     */
    public function getDatabasePlatform()
    {
        return $this->platformMock;
    }

    /**
     * @override
     */
    public function insert($tableName, array $data, array $types = array())
    {
        $this->inserts[$tableName][] = $data;
    }

    /**
     * @override
     */
    public function lastInsertId($seqName = null)
    {
        return $this->lastInsertId;
    }

    /**
     * @override
     */
    public function fetchColumn($statement, array $params = array(), $colnum = 0, array $types = array())
    {
        return $this->fetchOneResult;
    }

    /**
     * @override
     */
    public function quote($input, $type = null)
    {
        if (is_string($input)) {
            return "'" . $input . "'";
        }
        return $input;
    }

    /* Mock API */

    public function setFetchOneResult($fetchOneResult)
    {
        $this->fetchOneResult = $fetchOneResult;
    }

    public function setLastInsertId($id)
    {
        $this->lastInsertId = $id;
    }

    public function getInserts()
    {
        return $this->inserts;
    }

    public function reset()
    {
        $this->inserts = array();
        $this->lastInsertId = 0;
    }
}
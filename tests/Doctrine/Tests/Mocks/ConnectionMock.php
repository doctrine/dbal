<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Connection;
use function is_string;

class ConnectionMock extends Connection
{
    /** @var DatabasePlatformMock */
    private $_platformMock;

    /** @var int */
    private $_lastInsertId = 0;

    /** @var string[][] */
    private $_inserts = array();

    public function __construct(array $params, $driver, $config = null, $eventManager = null)
    {
        $this->_platformMock = new DatabasePlatformMock();

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function getDatabasePlatform()
    {
        return $this->_platformMock;
    }

    public function insert($tableName, array $data, array $types = [])
    {
        $this->_inserts[$tableName][] = $data;
    }

    public function lastInsertId($seqName = null)
    {
        return $this->_lastInsertId;
    }

    public function quote($input, $type = null)
    {
        if (is_string($input)) {
            return "'" . $input . "'";
        }
        return $input;
    }

    public function setLastInsertId($id)
    {
        $this->_lastInsertId = $id;
    }

    public function getInserts()
    {
        return $this->_inserts;
    }

    public function reset()
    {
        $this->_inserts      = [];
        $this->_lastInsertId = 0;
    }
}

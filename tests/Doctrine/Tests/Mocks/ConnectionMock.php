<?php

namespace Doctrine\Tests\Mocks;
use function is_string;

class ConnectionMock extends \Doctrine\DBAL\Connection
{
    /**
     * @var DatabasePlatformMock
     */
    private $_platformMock;

    /**
     * @var int
     */
    private $_lastInsertId = 0;

    /**
     * @var string[][]
     */
    private $_inserts = array();

    public function __construct(array $params, $driver, $config = null, $eventManager = null)
    {
        $this->_platformMock = new DatabasePlatformMock();

        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @override
     */
    public function getDatabasePlatform()
    {
        return $this->_platformMock;
    }

    /**
     * @override
     */
    public function insert($tableName, array $data, array $types = array())
    {
        $this->_inserts[$tableName][] = $data;
    }

    /**
     * @override
     */
    public function lastInsertId($seqName = null)
    {
        return $this->_lastInsertId;
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
        $this->_inserts = array();
        $this->_lastInsertId = 0;
    }
}

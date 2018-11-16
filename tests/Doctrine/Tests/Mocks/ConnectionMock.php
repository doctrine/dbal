<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use function is_string;

class ConnectionMock extends Connection
{
    /** @var DatabasePlatformMock */
    private $platformMock;

    /** @var int */
    private $lastInsertId = 0;

    /** @var string[][] */
    private $inserts = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $params, $driver, $config = null, $eventManager = null)
    {
        $this->platformMock = new DatabasePlatformMock();

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function getDatabasePlatform()
    {
        return $this->platformMock;
    }

    /**
     * {@inheritDoc}
     */
    public function insert($tableName, array $data, array $types = [])
    {
        $this->inserts[$tableName][] = $data;
    }

    public function lastInsertId(?string $seqName = null) : string
    {
        return (string) $this->lastInsertId;
    }

    public function quote($input, $type = ParameterType::STRING) : string
    {
        if (is_string($input)) {
            return "'" . $input . "'";
        }
        return (string) $input;
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
        $this->inserts      = [];
        $this->lastInsertId = 0;
    }
}

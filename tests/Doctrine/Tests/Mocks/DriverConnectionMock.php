<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\ParameterType;

class DriverConnectionMock implements Connection
{
    public function prepare($prepareString)
    {
    }
    public function query()
    {
    }

    public function quote($input, $type = ParameterType::STRING)
    {
    }

    public function exec($statement)
    {
    }
    public function lastInsertId($name = null)
    {
    }
    public function beginTransaction()
    {
    }
    public function commit()
    {
    }
    public function rollBack()
    {
    }
    public function errorCode()
    {
    }
    public function errorInfo()
    {
    }
}

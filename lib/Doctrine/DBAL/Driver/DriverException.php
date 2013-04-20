<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\DBALException;

class DriverException extends DBALException
{
    public static function driverExceptionDuringQuery(\Exception $driverEx, $sql, array $params = array())
    {
        $msg = "An exception occurred while executing '".$sql."'";
        if ($params) {
            $msg .= " with params ".json_encode($params);
        }
        $msg .= ":\n\n".$driverEx->getMessage();

        return new self($msg, (int) $driverEx->getCode(), $driverEx);
    }
}

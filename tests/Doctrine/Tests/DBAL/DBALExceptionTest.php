<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\DriverException;

class DBALExceptionTest extends \Doctrine\Tests\DbalTestCase
{
    public function testDriverExceptionDuringQueryAcceptsBinaryData()
    {
        $driver = $this->getMock('\Doctrine\DBAL\Driver');
        $e = DBALException::driverExceptionDuringQuery($driver, new \Exception, '', array('ABC', chr(128)));
        $this->assertContains('with params ["ABC", "\x80"]', $e->getMessage());
    }

    public function testAvoidOverWrappingOnDriverException()
    {
        $driver = $this->getMock('\Doctrine\DBAL\Driver');
        $ex = new DriverException('', $this->getMock('\Doctrine\DBAL\Driver\DriverException'));
        $e = DBALException::driverExceptionDuringQuery($driver, $ex, '');
        $this->assertSame($ex, $e);
    }
}

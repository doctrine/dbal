<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;

class DBALExceptionTest extends \Doctrine\Tests\DbalTestCase
{
    public function testDriverExceptionDuringQueryAcceptsBinaryData()
    {
        $driver = $this->getMock('\Doctrine\DBAL\Driver');
        $e = DBALException::driverExceptionDuringQuery($driver, new \Exception, '', array('ABC', chr(128)));
        $this->assertContains('with params ["ABC", "\x80"]', $e->getMessage());
    }
}

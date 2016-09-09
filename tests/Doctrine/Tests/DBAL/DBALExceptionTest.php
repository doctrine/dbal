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

    public function testDriverRequiredWithUrl()
    {
        $url = 'mysql://localhost';
        $exception = DBALException::driverRequired($url);

        $this->assertInstanceOf('Doctrine\DBAL\DBALException', $exception);
        $this->assertSame(
            sprintf(
                "The options 'driver' or 'driverClass' are mandatory if a connection URL without scheme " .
                "is given to DriverManager::getConnection(). Given URL: %s",
                $url
            ),
            $exception->getMessage()
        );
    }
}

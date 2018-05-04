<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Driver\DriverException as InnerDriverException;
use Doctrine\Tests\DbalTestCase;
use Doctrine\DBAL\Driver;
use function chr;
use function fopen;

class DBALExceptionTest extends DbalTestCase
{
    public function testDriverExceptionDuringQueryAcceptsBinaryData()
    {
        /* @var $driver Driver */
        $driver = $this->createMock(Driver::class);
        $e = DBALException::driverExceptionDuringQuery($driver, new \Exception, '', array('ABC', chr(128)));
        self::assertContains('with params ["ABC", "\x80"]', $e->getMessage());
    }
    
    public function testDriverExceptionDuringQueryAcceptsResource()
    {
        /* @var $driver Driver */
        $driver = $this->createMock(Driver::class);
        $e = \Doctrine\DBAL\DBALException::driverExceptionDuringQuery($driver, new \Exception, "INSERT INTO file (`content`) VALUES (?)", [1 => fopen(__FILE__, 'r')]);
        self::assertContains('Resource', $e->getMessage());
    }

    public function testAvoidOverWrappingOnDriverException()
    {
        /* @var $driver Driver */
        $driver = $this->createMock(Driver::class);
        $inner = new class extends \Exception implements InnerDriverException
        {
            /**
             * {@inheritDoc}
             */
            public function getErrorCode()
            {
            }

            /**
             * {@inheritDoc}
             */
            public function getSQLState()
            {
            }
        };
        $ex = new DriverException('', $inner);
        $e = DBALException::driverExceptionDuringQuery($driver, $ex, '');
        self::assertSame($ex, $e);
    }
}

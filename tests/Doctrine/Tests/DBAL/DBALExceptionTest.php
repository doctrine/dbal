<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Type;

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

    /**
     * @dataProvider getCloseMatchSuggestions
     */
    public function testUnknownColumnTypeSuggestsClosestMatch($requestedType, $expectedTypeSuggest)
    {
        $exception = DBALException::unknownColumnType($requestedType);
        $this->assertContains("Did you mean \"$expectedTypeSuggest\"?", $exception->getMessage());
    }
    
    public function getCloseMatchSuggestions()
    {
        return array(
            array('bool', Type::BOOLEAN),
            array('str', Type::STRING),
            array('aray', Type::TARRAY),
            array('tetx', Type::TEXT)
        );
    }

    /**
     * @dataProvider getFarMatches
     */
    public function testUnknownColumnTypeIgnoresFarMatches($requestedType)
    {
        $exception = DBALException::unknownColumnType($requestedType);
        $this->assertNotContains("Did you mean", $exception->getMessage());
    }
    
    public function getFarMatches()
    {
        return array(
            array('itgaer'),
            array('yarra'),
            array('daetiem'),
            array('cdeaimal'),
            array('jasdnviw')
        );
    }
}

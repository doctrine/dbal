<?php

namespace Doctrine\Tests\DBAL\Connection;

use Doctrine\DBAL\Connections\ConnectorFactory;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\ConnectorException;
use Doctrine\Tests\DbalTestCase;

class ConnectorFactoryTest extends DbalTestCase
{
    public function testCreateHighAvailability() : void
    {
        $driverMock = $this->createMock(Driver::class);
        $params     = ['strategy' => 'high_availability'];

        $this->assertInstanceOf('Doctrine\DBAL\Connections\Connector\HighAvailabilityConnector', ConnectorFactory::create($params, $driverMock));
    }

    public function testCreateRandom() : void
    {
        $driverMock = $this->createMock(Driver::class);
        $params     = ['strategy' => 'random'];

        $this->assertInstanceOf('Doctrine\DBAL\Connections\Connector\RandomConnector', ConnectorFactory::create($params, $driverMock));
    }

    public function testCreateDefault() : void
    {
        $driverMock = $this->createMock(Driver::class);
        $params     = [];

        $this->assertInstanceOf('Doctrine\DBAL\Connections\Connector\RandomConnector', ConnectorFactory::create($params, $driverMock));
    }

    public function testCreateUnknown() : void
    {
        $this->expectException(ConnectorException::class);
        $driverMock = $this->createMock(Driver::class);
        $params     = ['strategy' => '12345'];

        ConnectorFactory::create($params, $driverMock);
    }
}

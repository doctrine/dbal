<?php

namespace Doctrine\Tests\DBAL\Connection\Connector;

use Doctrine\DBAL\Connections\Connector\HighAvailabilityConnector;
use Throwable;

class HighAvailabilityConnectorTest extends ConnectorTest
{
    public function testConnect() : void
    {
        $params = $this->getParams();

        $connector = new HighAvailabilityConnector($params, $this->failingDriverMock());
        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('master'));
        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('slave'));
    }

    public function testConnectFailMaster() : void
    {
        $params = $this->getParams(2, 0, true);

        $connector = new HighAvailabilityConnector($params, $this->failingDriverMock());
        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('slave'));

        $failed = false;
        try {
            $connector->connectTo('master');
            $failed = true;
        } catch (Throwable $e) {
            $this->assertInstanceOf('Doctrine\DBAL\Exception\ConnectionException', $e);
        }

        if (! $failed) {
            return;
        }

        $this->fail('Connect has not failed');
    }

    public function testConnectFailSlave() : void
    {
        $params = $this->getParams(2, 2);

        $connector = new HighAvailabilityConnector($params, $this->failingDriverMock());
        $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('master'));

        $failed = false;
        try {
            $connector->connectTo('slave');
            $failed = true;
        } catch (Throwable $e) {
            $this->assertInstanceOf('Doctrine\DBAL\Exception\ConnectionException', $e);
        }

        if (! $failed) {
            return;
        }

        $this->fail('Connect has not failed');
    }

    public function testConnectRandomFail() : void
    {
        for ($i = 0; $i < 100; $i++) {
            $params = $this->getParams(2, 1);

            $connector = new HighAvailabilityConnector($params, $this->failingDriverMock());
            $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('master'));
            $this->assertInstanceOf('Doctrine\DBAL\Driver\Connection', $connector->connectTo('slave'));
        }
    }
}

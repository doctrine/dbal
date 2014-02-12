<?php

namespace Doctrine\Tests\DBAL\Driver\SQLSrv;

use Doctrine\Tests\DbalTestCase;

class SQLSrvConnectionTest extends DbalTestCase
{
    /**
     * The sqlsrv driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if ( ! extension_loaded('sqlsrv')) {
            $this->markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder('Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        $this->assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}

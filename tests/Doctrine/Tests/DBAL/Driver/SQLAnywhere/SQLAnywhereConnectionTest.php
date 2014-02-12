<?php

namespace Doctrine\Tests\DBAL\Driver\SQLAnywhere;

use Doctrine\Tests\DbalTestCase;

class SQLAnywhereConnectionTest extends DbalTestCase
{
    /**
     * The sqlanywhere driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if ( ! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder('Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testRequiresQueryForServerVersion()
    {
        $this->assertTrue($this->connectionMock->requiresQueryForServerVersion());
    }
}

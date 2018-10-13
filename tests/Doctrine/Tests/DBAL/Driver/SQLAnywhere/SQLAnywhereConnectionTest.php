<?php

namespace Doctrine\Tests\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use function extension_loaded;

class SQLAnywhereConnectionTest extends DbalTestCase
{
    /**
     * The sqlanywhere driver connection mock under test.
     *
     * @var SQLAnywhereConnection|PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLAnywhereConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testRequiresQueryForServerVersion()
    {
        self::assertTrue($this->connectionMock->requiresQueryForServerVersion());
    }
}

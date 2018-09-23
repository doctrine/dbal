<?php

namespace Doctrine\Tests\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use function extension_loaded;

class SQLSrvConnectionTest extends DbalTestCase
{
    /**
     * The sqlsrv driver connection mock under test.
     *
     * @var SQLSrvConnection|PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if (! extension_loaded('sqlsrv')) {
            $this->markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLSrvConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}

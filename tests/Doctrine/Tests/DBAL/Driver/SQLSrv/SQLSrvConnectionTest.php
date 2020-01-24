<?php

namespace Doctrine\Tests\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function extension_loaded;

class SQLSrvConnectionTest extends DbalTestCase
{
    /**
     * The sqlsrv driver connection mock under test.
     *
     * @var SQLSrvConnection|MockObject
     */
    private $connectionMock;

    protected function setUp() : void
    {
        if (! extension_loaded('sqlsrv')) {
            $this->markTestSkipped('sqlsrv is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLSrvConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion() : void
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}

<?php

namespace Doctrine\Tests\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\SQLSrv\SQLSrvConnection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SQLSrvConnectionTest extends DbalTestCase
{
    /**
     * The sqlsrv driver connection mock under test.
     *
     * @var SQLSrvConnection|MockObject
     */
    private $connectionMock;

    /**
     * @requires extension sqlsrv
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLSrvConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion(): void
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}

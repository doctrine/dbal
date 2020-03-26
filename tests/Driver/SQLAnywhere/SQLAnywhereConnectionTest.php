<?php

namespace Doctrine\DBAL\Tests\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function extension_loaded;

class SQLAnywhereConnectionTest extends TestCase
{
    /**
     * The sqlanywhere driver connection mock under test.
     *
     * @var SQLAnywhereConnection|MockObject
     */
    private $connectionMock;

    protected function setUp() : void
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLAnywhereConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testRequiresQueryForServerVersion() : void
    {
        self::assertTrue($this->connectionMock->requiresQueryForServerVersion());
    }
}

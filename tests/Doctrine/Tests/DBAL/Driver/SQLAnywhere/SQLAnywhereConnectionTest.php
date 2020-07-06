<?php

namespace Doctrine\Tests\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\SQLAnywhereConnection;
use Doctrine\Tests\DbalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @requires extension sqlanywhere
 */
class SQLAnywhereConnectionTest extends DbalTestCase
{
    /**
     * The sqlanywhere driver connection mock under test.
     *
     * @var SQLAnywhereConnection|MockObject
     */
    private $connectionMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionMock = $this->getMockBuilder(SQLAnywhereConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testRequiresQueryForServerVersion(): void
    {
        self::assertTrue($this->connectionMock->requiresQueryForServerVersion());
    }
}

<?php

namespace Doctrine\Tests\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @requires extension mysqli
 */
class MysqliConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The mysqli driver connection mock under test.
     *
     * @var MysqliConnection&MockObject
     */
    private $connectionMock;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            $this->markTestSkipped('MySQL only test.');
        }

        $this->connectionMock = $this->getMockBuilder(MysqliConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion(): void
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }
}

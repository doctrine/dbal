<?php

namespace Doctrine\Tests\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use function extension_loaded;
use function restore_error_handler;
use function set_error_handler;

class MysqliConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The mysqli driver connection mock under test.
     *
     * @var MysqliConnection|PHPUnit_Framework_MockObject_MockObject
     */
    private $connectionMock;

    protected function setUp()
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            $this->markTestSkipped('MySQL only test.');
        }

        $this->connectionMock = $this->getMockBuilder(MysqliConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion()
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }

    public function testRestoresErrorHandlerOnException()
    {
        $handler         = static function () {
            self::fail('Never expected this to be called');
        };
        $default_handler = set_error_handler($handler);

        try {
            new MysqliConnection(['host' => '255.255.255.255'], 'user', 'pass');
            self::fail('An exception was supposed to be raised');
        } catch (MysqliException $e) {
            self::assertSame('Network is unreachable', $e->getMessage());
        }

        self::assertSame($handler, set_error_handler($default_handler), 'Restoring error handler failed.');
        restore_error_handler();
        restore_error_handler();
    }
}

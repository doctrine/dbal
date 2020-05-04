<?php

namespace Doctrine\DBAL\Tests\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\HostRequired;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function extension_loaded;
use function restore_error_handler;
use function set_error_handler;

class MysqliConnectionTest extends FunctionalTestCase
{
    /**
     * The mysqli driver connection mock under test.
     *
     * @var MysqliConnection|MockObject
     */
    private $connectionMock;

    protected function setUp() : void
    {
        if (! extension_loaded('mysqli')) {
            self::markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if (! $this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            $this->markTestSkipped('MySQL only test.');
        }

        $this->connectionMock = $this->getMockBuilder(MysqliConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    public function testDoesNotRequireQueryForServerVersion() : void
    {
        self::assertFalse($this->connectionMock->requiresQueryForServerVersion());
    }

    public function testRestoresErrorHandlerOnException() : void
    {
        $handler = static function () : bool {
            self::fail('Never expected this to be called');
        };

        $defaultHandler = set_error_handler($handler);

        try {
            new MysqliConnection(['host' => '255.255.255.255'], 'user', 'pass');
            self::fail('An exception was supposed to be raised');
        } catch (MysqliException $e) {
            self::assertSame('Network is unreachable', $e->getMessage());
        }

        self::assertSame($handler, set_error_handler($defaultHandler), 'Restoring error handler failed.');
        restore_error_handler();
        restore_error_handler();
    }

    public function testHostnameIsRequiredForPersistentConnection() : void
    {
        $this->expectException(HostRequired::class);
        new MysqliConnection(['persistent' => 'true'], '', '');
    }
}

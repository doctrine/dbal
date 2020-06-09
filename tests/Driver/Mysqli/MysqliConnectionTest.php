<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\HostRequired;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;
use function restore_error_handler;
use function set_error_handler;

class MysqliConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('mysqli')) {
            self::markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return;
        }

        self::markTestSkipped('MySQL only test.');
    }

    public function testRestoresErrorHandlerOnException(): void
    {
        $handler = static function (): bool {
            self::fail('Never expected this to be called');
        };
        set_error_handler($handler);

        try {
            new MysqliConnection(['host' => '255.255.255.255'], 'user', 'pass');
            self::fail('An exception was supposed to be raised');
        } catch (ConnectionError $e) {
            // Do nothing
        }

        self::assertSame($handler, set_error_handler($handler), 'Restoring error handler failed.');
        restore_error_handler();
        restore_error_handler();
    }

    public function testHostnameIsRequiredForPersistentConnection(): void
    {
        $this->expectException(HostRequired::class);
        new MysqliConnection(['persistent' => 'true'], '', '');
    }
}

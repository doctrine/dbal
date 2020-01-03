<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DbalFunctionalTestCase;
use function extension_loaded;
use function restore_error_handler;
use function set_error_handler;

class MysqliConnectionTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return;
        }

        $this->markTestSkipped('MySQL only test.');
    }

    public function testRestoresErrorHandlerOnException() : void
    {
        $handler         = static function () : bool {
            self::fail('Never expected this to be called');
        };
        $default_handler = set_error_handler($handler);

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
}

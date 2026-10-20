<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\LoadExtension;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class LoadExtensionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_sqlite', 'sqlite3')) {
            return;
        }

        self::markTestSkipped('This test requires a SQLite driver.');
    }

    public function testLoadExtension(): void
    {
        if (! isset($GLOBALS['sqlite_extension'])) {
            self::markTestSkipped('This test requires the path of a SQLite extension (sqlite_extension).');
        }

        $connection = $this->createConnection(new LoadExtension($GLOBALS['sqlite_extension']));

        self::assertNotFalse($connection->fetchOne('SELECT spatialite_version()'));
    }

    public function testMissingExtension(): void
    {
        $connection = $this->createConnection(new LoadExtension('/nonexistent/mod_missing.so'));

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('mod_missing.so');

        $connection->fetchOne('SELECT 1');
    }

    private function createConnection(LoadExtension $middleware): Connection
    {
        return DriverManager::getConnection(
            $this->connection->getParams(),
            (new Configuration())->setMiddlewares([$middleware]),
        );
    }
}

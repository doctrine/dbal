<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Pdo\Mysql;
use Pdo\Pgsql;
use Pdo\Sqlite;
use PHPUnit\Framework\Attributes\RequiresPhp;

#[RequiresPhp('8.4')]
final class PDOSubclassTest extends FunctionalTestCase
{
    public function testMySQLSubclass(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_mysql')) {
            self::markTestSkipped('This test requires the pdo_mysql driver.');
        }

        self::assertInstanceOf(Mysql::class, $this->connection->getNativeConnection());
    }

    public function testPgSQLSubclass(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql')) {
            self::markTestSkipped('This test requires the pdo_pgsql driver.');
        }

        self::assertInstanceOf(Pgsql::class, $this->connection->getNativeConnection());
    }

    public function testSQLiteSubclass(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_sqlite')) {
            self::markTestSkipped('This test requires the pdo_sqlite driver.');
        }

        self::assertInstanceOf(Sqlite::class, $this->connection->getNativeConnection());
    }
}

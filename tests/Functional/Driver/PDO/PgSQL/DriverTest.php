<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_pgsql')]
class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pdo_pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql driver.');
    }

    protected function createDriver(): Driver
    {
        return new Driver();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PgSQL;

use Doctrine\DBAL\Driver\PgSQL\Driver;
use Doctrine\DBAL\Tests\Functional\Driver\AbstractPostgreSQLDriverTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pgsql')]
class DriverTest extends AbstractPostgreSQLDriverTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pgsql driver.');
    }

    protected function createDriver(): Driver
    {
        return new Driver();
    }
}

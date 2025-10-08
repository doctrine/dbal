<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Platforms\SQLitePlatform;

/** @extends AbstractDriverTestCase<SQLitePlatform> */
abstract class AbstractSQLiteDriverTestCase extends AbstractDriverTestCase
{
    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        self::markTestSkipped('SQLite drivers do not use server version to instantiate platform');
    }
}

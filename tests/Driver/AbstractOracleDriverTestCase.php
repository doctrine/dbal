<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver;

use Doctrine\DBAL\Platforms\OraclePlatform;

/** @extends AbstractDriverTestCase<OraclePlatform> */
abstract class AbstractOracleDriverTestCase extends AbstractDriverTestCase
{
    public function testThrowsExceptionOnCreatingDatabasePlatformsForInvalidVersion(): void
    {
        self::markTestSkipped('Oracle drivers do not use server version to instantiate platform');
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;

class PostgreSqlPlatformTest extends AbstractPostgreSqlPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new PostgreSqlPlatform();
    }

    public function testSupportsPartialIndexes() : void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }
}

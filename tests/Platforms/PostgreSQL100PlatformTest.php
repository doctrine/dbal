<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;

class PostgreSQL100PlatformTest extends PostgreSQLPlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new PostgreSQL100Platform();
    }
}

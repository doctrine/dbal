<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

class PostgreSQLPlatformTest extends AbstractPostgreSQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new PostgreSQLPlatform();
    }
}

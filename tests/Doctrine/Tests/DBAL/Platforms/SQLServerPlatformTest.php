<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServerPlatform;
    }

}

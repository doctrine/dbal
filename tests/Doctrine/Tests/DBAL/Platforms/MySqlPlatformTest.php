<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySqlPlatform;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform()
    {
        return new MysqlPlatform;
    }
}

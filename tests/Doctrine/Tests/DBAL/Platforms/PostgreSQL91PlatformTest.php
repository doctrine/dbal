<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\PostgreSQL91Platform;

class PostgreSql91PlatformTest extends PostgreSqlPlatformTest
{
    public function createPlatform()
    {
        return new PostgreSQL91Platform();
    }

    public function testColumnCollationDeclarationSQL()
    {
        $this->assertEquals(
            'COLLATE "en_US.UTF-8"',
            $this->_platform->getColumnCollationDeclarationSQL('en_US.UTF-8')
        );
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2008Platform;

class SQLServer2008PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServer2008Platform();
    }

    public function testGeneratesTypeDeclarationForDateTimeTz()
    {
        self::assertEquals('DATETIMEOFFSET(6)', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;

class SQLServer2008PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new SQLServer2008Platform();
    }

    public function testGeneratesTypeDeclarationForDateTimeTz() : void
    {
        self::assertEquals('DATETIMEOFFSET(6)', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }
}

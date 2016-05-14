<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2008Platform;

class SQLServer2008PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServer2008Platform;
    }

    public function testGeneratesTypeDeclarationForDateTimeTz()
    {
        $this->assertEquals(
            'DATETIMEOFFSET(6)',
            $this->_platform->getDateTimeTzTypeDeclarationSQL(
                array())
        );
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2) WHERE test IS NOT NULL AND test2 IS NOT NULL';
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Types\Type;

class SQLServer2008PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServer2008Platform;
    }

    public function testGeneratesTypeDeclarationForDateTimeTz()
    {
        self::assertEquals(
            'DATETIMEOFFSET(6)',
            $this->_platform->getDateTimeTzTypeDeclarationSQL(
                array())
        );
    }

    public function testGetDefaultValueDeclarationSQLForDateType()
    {
        $currentDateSql = $this->_platform->getCurrentDateSQL();
        $field = array(
            'type'    => Type::getType('date'),
            'default' => $currentDateSql,
        );

        $this->assertEquals(
            " DEFAULT '".$currentDateSql."'",
            $this->_platform->getDefaultValueDeclarationSQL($field)
        );
    }
}

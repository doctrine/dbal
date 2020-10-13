<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Schema\Index;

use function substr_count;

class SQLServer2008PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServer2008Platform();
    }

    public function testGeneratesTypeDeclarationForDateTimeTz(): void
    {
        self::assertEquals('DATETIMEOFFSET(6)', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }

    public function testSupportsPartialIndexes(): void
    {
        self::assertTrue($this->platform->supportsPartialIndexes());
    }

    /**
     * Tests if automatically added conditions for the unique constraint are correctly merged
     * with optional where conditions.
     */
    public function testGeneratesPartialUniqueConstraintSqlWithSingleWherePart(): void
    {
        $where       = 'test IS NULL AND test2 IS NOT NULL';
        $uniqueIndex = new Index('name', ['test', 'test2'], true, false, [], ['where' => $where]);

        $sql = $this->platform->getUniqueConstraintDeclarationSQL('name', $uniqueIndex);

        self::assertEquals(1, substr_count($sql, 'WHERE '));
    }
}

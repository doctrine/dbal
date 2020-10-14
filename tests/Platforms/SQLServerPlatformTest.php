<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Index;

use function substr_count;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    /**
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint(?int $lockMode, string $lockHint): void
    {
        $fromClause     = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        self::assertSame($expectedResult, $this->platform->appendLockHint($fromClause, $lockMode));
    }

    /**
     * @return mixed[][]
     */
    public static function getLockHints(): iterable
    {
        return [
            [null, ''],
            [LockMode::NONE, ' WITH (NOLOCK)'],
            [LockMode::OPTIMISTIC, ''],
            [LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'],
            [LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'],
        ];
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
     * Tests if automatically added conditions for unique indexes (not unique constraints) are correctly merged
     * with optional where conditions.
     */
    public function testGeneratesPartialUniqueIndexSqlWithSingleWherePart(): void
    {
        $where       = 'test IS NULL AND test2 IS NOT NULL';
        $uniqueIndex = new Index('name', ['test', 'test2'], true, false, [], ['where' => $where]);

        $sql = $this->platform->getCreateIndexSQL($uniqueIndex, 'foo');

        self::assertEquals(1, substr_count($sql, 'WHERE '));
    }
}

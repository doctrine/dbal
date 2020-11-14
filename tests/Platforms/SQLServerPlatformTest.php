<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    /**
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint(int $lockMode, string $lockHint): void
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
            [LockMode::NONE, ''],
            [LockMode::OPTIMISTIC, ''],
            [LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'],
            [LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'],
        ];
    }

    public function testGeneratesTypeDeclarationForDateTimeTz(): void
    {
        self::assertEquals('DATETIMEOFFSET(6)', $this->platform->getDateTimeTzTypeDeclarationSQL([]));
    }
}

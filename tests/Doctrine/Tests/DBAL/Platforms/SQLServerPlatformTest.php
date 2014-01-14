<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServerPlatform;
    }

    /**
     * @group DDC-2310
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint($lockMode, $lockHint)
    {
        $fromClause     = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        $this->assertSame($expectedResult, $this->_platform->appendLockHint($fromClause, $lockMode));
    }

    public function getLockHints()
    {
        return array(
            array(null, ''),
            array(false, ''),
            array(true, ''),
            array(LockMode::NONE, ' WITH (NOLOCK)'),
            array(LockMode::OPTIMISTIC, ''),
            array(LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'),
            array(LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'),
        );
    }
}

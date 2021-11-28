<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use InvalidArgumentException;

class MySQLPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    public function testHasCorrectDefaultTransactionIsolationLevel(): void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel()
        );
    }

    public function testDropIndexSQLRequiresTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->platform->getDropIndexSQL('foo');
    }
}

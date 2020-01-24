<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new MySqlPlatform();
    }

    public function testHasCorrectDefaultTransactionIsolationLevel() : void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel()
        );
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform()
    {
        return new MysqlPlatform;
    }

    public function testHasCorrectDefaultTransactionIsolationLevel()
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->_platform->getDefaultTransactionIsolationLevel()
        );
    }
}

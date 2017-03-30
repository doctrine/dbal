<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Connection;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform()
    {
        return new MysqlPlatform;
    }

    public function testHasCorrectDefaultTransactionIsolationLevel()
    {
        $this->assertEquals(
            Connection::TRANSACTION_REPEATABLE_READ,
            $this->_platform->getDefaultTransactionIsolationLevel()
        );
    }
}

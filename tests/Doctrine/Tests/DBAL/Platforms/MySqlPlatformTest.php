<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform()
    {
        return new MySqlPlatform();
    }

    public function testHasCorrectDefaultTransactionIsolationLevel()
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel()
        );
    }

    public function testPreserveIndexOnAlterTable()
    {
        $index = new Index('index_name', ['column']);
        $diff = new TableDiff('table_name', [], [], [], [], [$index]);
        $this->assertEquals([], $this->createPlatform()->getAlterTableSQL($diff));
    }
}

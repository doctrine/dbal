<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Table;
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

    public function testCollationOptionIsTakenIntoAccount(): void
    {
        $table = new Table('quotations');
        $table->addColumn('id', 'integer');
        $table->addOption('collation', 'my_collation');
        self::assertStringContainsString(
            'my_collation',
            $this->platform->getCreateTableSQL($table)[0]
        );
    }

    public function testCollateOptionIsStillSupported(): void
    {
        $table = new Table('quotations');
        $table->addColumn('id', 'integer');
        $table->addOption('collate', 'my_collation');
        self::assertStringContainsString(
            'my_collation',
            $this->platform->getCreateTableSQL($table)[0]
        );
    }
}

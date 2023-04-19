<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use InvalidArgumentException;

class MySQLPlatformTest extends AbstractMySQLPlatformTestCase
{
    use VerifyDeprecations;

    public function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    public function testHasCorrectDefaultTransactionIsolationLevel(): void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel(),
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
        $table->addColumn('id', Types::INTEGER);
        $table->addOption('collation', 'my_collation');
        self::assertStringContainsString(
            'my_collation',
            $this->platform->getCreateTableSQL($table)[0],
        );
    }

    public function testCollateOptionIsStillSupportedButDeprecated(): void
    {
        $table = new Table('quotations');
        $table->addColumn('id', Types::INTEGER);
        $table->addOption('collate', 'my_collation');
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/issues/5214');
        self::assertStringContainsString(
            'my_collation',
            $this->platform->getCreateTableSQL($table)[0],
        );
    }
}

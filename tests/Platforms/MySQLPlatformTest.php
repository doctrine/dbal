<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
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

    public function testCollateOptionIsStillSupportedButDeprecated(): void
    {
        $table = new Table('quotations');
        $table->addColumn('id', 'integer');
        $table->addOption('collate', 'my_collation');
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/issues/5214');
        self::assertStringContainsString(
            'my_collation',
            $this->platform->getCreateTableSQL($table)[0]
        );
    }

    public function testOmittingTheCharsetIsDeprecated(): void
    {
        $table = new Table('a_table', [new Column('a_column', Type::getType('string'))]);
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/5278');
        $this->platform->getCreateTableSQL($table);
    }
}

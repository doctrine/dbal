<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;

class MySQLPlatformTest extends AbstractMySQLPlatformTestCase
{
    use VerifyDeprecations;

    public function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return ['ALTER TABLE mytable RENAME INDEX idx_foo TO idx_bar'];
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'ALTER TABLE `table` RENAME INDEX `create` TO `select`',
            'ALTER TABLE `table` RENAME INDEX `foo` TO `bar`',
        ];
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return ['ALTER TABLE myschema.mytable RENAME INDEX idx_foo TO idx_bar'];
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'ALTER TABLE `schema`.`table` RENAME INDEX `create` TO `select`',
            'ALTER TABLE `schema`.`table` RENAME INDEX `foo` TO `bar`',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return ['ALTER TABLE mytable RENAME INDEX idx_foo TO idx_foo_renamed'];
    }

    public function testHasCorrectDefaultTransactionIsolationLevel(): void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel(),
        );
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
}

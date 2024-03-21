<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Types;

class MariaDBPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDBPlatform();
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

    /**
     * From MariaDB 10.2.7, JSON type is an alias to LONGTEXT however from 10.4.3 setting a column
     * as JSON adds additional functionality so use JSON.
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    public function testIgnoresDifferenceInDefaultValuesForUnsupportedColumnTypes(): void
    {
        self::markTestSkipped('MariaDB supports default values for BLOB and TEXT columns');
    }
}

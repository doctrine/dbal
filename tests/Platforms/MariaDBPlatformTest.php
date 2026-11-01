<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Types;
use Override;

class MariaDBPlatformTest extends AbstractMySQLPlatformTestCase
{
    #[Override]
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDBPlatform();
    }

    /** @return string[] */
    #[Override]
    protected function getAlterTableRenameIndexSQL(): array
    {
        return ['ALTER TABLE `mytable` RENAME INDEX `idx_foo` TO `idx_bar`'];
    }

    /** @return string[] */
    #[Override]
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return ['ALTER TABLE `table` RENAME INDEX `create` TO `select`, RENAME INDEX `foo` TO `bar`'];
    }

    /** @return string[] */
    #[Override]
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return ['ALTER TABLE `myschema`.`mytable` RENAME INDEX `idx_foo` TO `idx_bar`'];
    }

    /** @return string[] */
    #[Override]
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return ['ALTER TABLE `schema`.`table` RENAME INDEX `create` TO `select`, RENAME INDEX `foo` TO `bar`'];
    }

    /**
     * From MariaDB 10.2.7, JSON type is an alias to LONGTEXT however from 10.4.3 setting a column
     * as JSON adds additional functionality so use JSON.
     *
     * @link https://mariadb.com/kb/en/library/json-data-type/
     */
    #[Override]
    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        self::assertSame('JSON', $this->platform->getJsonTypeDeclarationSQL([]));
    }

    public function testInitializesJsonTypeMapping(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Types::JSON, $this->platform->getDoctrineTypeMapping('json'));
    }

    #[Override]
    public function testIgnoresDifferenceInDefaultValuesForUnsupportedColumnTypes(): void
    {
        self::markTestSkipped('MariaDB supports default values for BLOB and TEXT columns');
    }

    #[Override]
    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE `test` ADD FOREIGN KEY (`fk_name_id`) REFERENCES `other_table` (`id`)'
            . ' ON UPDATE NO ACTION ON DELETE NO ACTION';
    }

    #[Override]
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, `foo` VARCHAR(255) NOT NULL, '
                . '`bar` VARCHAR(255) NOT NULL)',
            'ALTER TABLE `quoted` ADD CONSTRAINT `FK_WITH_RESERVED_KEYWORD` FOREIGN KEY (`create`, `foo`, `bar`)'
                . ' REFERENCES `foreign` (`create`, `bar`, `foo-bar`) ON UPDATE NO ACTION ON DELETE NO ACTION',
            'ALTER TABLE `quoted` ADD CONSTRAINT `FK_WITH_NON_RESERVED_KEYWORD` FOREIGN KEY (`create`, `foo`, `bar`)'
                . ' REFERENCES `foo` (`create`, `bar`, `foo-bar`) ON UPDATE NO ACTION ON DELETE NO ACTION',
            'ALTER TABLE `quoted` ADD CONSTRAINT `FK_WITH_INTENDED_QUOTATION` FOREIGN KEY (`create`, `foo`, `bar`)'
                . ' REFERENCES `foo-bar` (`create`, `bar`, `foo-bar`) ON UPDATE NO ACTION ON DELETE NO ACTION',
        ];
    }
}

<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Types\Type;

class MySQL57PlatformTest extends AbstractMySQLPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new MySQL57Platform();
    }

    public function testHasNativeJsonType()
    {
        self::assertTrue($this->_platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL()
    {
        self::assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array()));
    }

    public function testInitializesJsonTypeMapping()
    {
        self::assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        self::assertSame(Type::JSON, $this->_platform->getDoctrineTypeMapping('json'));
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER TABLE mytable RENAME INDEX idx_foo TO idx_bar',
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return array(
            'ALTER TABLE `table` RENAME INDEX `create` TO `select`',
            'ALTER TABLE `table` RENAME INDEX `foo` TO `bar`',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'ALTER TABLE myschema.mytable RENAME INDEX idx_foo TO idx_bar',
        );
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return array(
            'ALTER TABLE `schema`.`table` RENAME INDEX `create` TO `select`',
            'ALTER TABLE `schema`.`table` RENAME INDEX `foo` TO `bar`',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return array(
            'ALTER TABLE mytable RENAME INDEX idx_foo TO idx_foo_renamed',
        );
    }

    /**
     * @group DBAL-2576
     */
    public function testNotDuplicateDropForeignKeySql()
    {
        $diff = $this->createNotDuplicateDropForeignKeySql();

        // Run through alter table method
        $sql = $this->_platform->getAlterTableSQL($diff);

        // Assert that there are no duplicates in the results
        $this->assertSame([
            'ALTER TABLE documents DROP FOREIGN KEY documents_ibfk_1',
            'ALTER TABLE documents ADD CONSTRAINT FK_a788692ffa0c224 FOREIGN KEY (office_id) REFERENCES offices (id) ON DELETE CASCADE',
            'ALTER TABLE documents RENAME INDEX office_id TO IDX_A788692FFA0C224',
        ], $sql);
    }
}

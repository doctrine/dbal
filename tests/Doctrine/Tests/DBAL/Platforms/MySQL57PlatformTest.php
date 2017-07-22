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
        $this->assertTrue($this->_platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL()
    {
        $this->assertSame('JSON', $this->_platform->getJsonTypeDeclarationSQL(array()));
    }

    public function testInitializesJsonTypeMapping()
    {
        $this->assertTrue($this->_platform->hasDoctrineTypeMappingFor('json'));
        $this->assertSame(Type::JSON, $this->_platform->getDoctrineTypeMapping('json'));
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
}

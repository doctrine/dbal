<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB1052Platform;

class MariaDB1052PlatformTest extends MariaDBPlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDB1052Platform();
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
}

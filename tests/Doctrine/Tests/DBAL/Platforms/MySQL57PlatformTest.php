<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MySQL57Platform;

class MySQL57PlatformTest extends AbstractMySQLPlatformTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createPlatform()
    {
        return new MySQL57Platform();
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
}

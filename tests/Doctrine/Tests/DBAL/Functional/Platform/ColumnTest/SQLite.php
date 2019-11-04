<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class SQLite extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(SqlitePlatform::class);
    }
}

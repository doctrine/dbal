<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;

final class SQLite extends AbstractColumnTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(SQLitePlatform::class);
    }
}

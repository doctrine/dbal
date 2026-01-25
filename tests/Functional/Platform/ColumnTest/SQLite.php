<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;
use Override;

final class SQLite extends AbstractColumnTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(SQLitePlatform::class);
    }
}

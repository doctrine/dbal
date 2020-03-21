<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class PostgreSQL extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(PostgreSQL94Platform::class);
    }
}

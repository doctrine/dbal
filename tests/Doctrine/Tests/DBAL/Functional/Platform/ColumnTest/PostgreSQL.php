<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class PostgreSQL extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(PostgreSqlPlatform::class);
    }
}

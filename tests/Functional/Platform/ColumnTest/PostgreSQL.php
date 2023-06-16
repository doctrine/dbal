<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;

final class PostgreSQL extends AbstractColumnTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(PostgreSQLPlatform::class);
    }
}

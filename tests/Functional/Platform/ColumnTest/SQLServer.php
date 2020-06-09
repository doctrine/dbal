<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

final class SQLServer extends ColumnTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(SQLServer2012Platform::class);
    }

    public function testVariableLengthStringNoLength(): void
    {
        self::markTestSkipped();
    }

    public function testVariableLengthBinaryNoLength(): void
    {
        self::markTestSkipped();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;

final class SQLServer extends AbstractColumnTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(SQLServerPlatform::class);
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

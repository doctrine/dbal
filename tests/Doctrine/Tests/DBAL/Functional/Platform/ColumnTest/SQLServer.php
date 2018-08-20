<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class SQLServer extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(SQLServerPlatform::class);
    }

    public function testVariableLengthStringNoLength() : void
    {
        self::markTestSkipped();
    }

    public function testVariableLengthBinaryNoLength() : void
    {
        self::markTestSkipped();
    }
}

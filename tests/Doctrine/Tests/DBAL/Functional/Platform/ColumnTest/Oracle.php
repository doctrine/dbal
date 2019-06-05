<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class Oracle extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(OraclePlatform::class);
    }

    public function testVariableLengthStringNoLength() : void
    {
        self::markTestSkipped();
    }

    public function testVariableLengthBinaryNoLength() : void
    {
        self::markTestSkipped();
    }

    public function testFixedLengthBinaryNoLength() : void
    {
        self::markTestSkipped();
    }
}

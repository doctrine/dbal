<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class MySQL extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(MySqlPlatform::class);
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

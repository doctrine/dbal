<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\Tests\DBAL\Functional\Platform\ColumnTest;

final class IBMDB2 extends ColumnTest
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->requirePlatform(DB2Platform::class);
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

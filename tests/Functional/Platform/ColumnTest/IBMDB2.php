<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;
use Override;

final class IBMDB2 extends AbstractColumnTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(DB2Platform::class);
    }

    #[Override]
    public function testVariableLengthStringNoLength(): void
    {
        self::markTestSkipped();
    }

    #[Override]
    public function testVariableLengthBinaryNoLength(): void
    {
        self::markTestSkipped();
    }
}

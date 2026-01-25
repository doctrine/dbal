<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform\ColumnTest;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tests\Functional\Platform\AbstractColumnTestCase;
use Override;

final class MySQL extends AbstractColumnTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlatform(AbstractMySQLPlatform::class);
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

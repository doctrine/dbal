<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB1070Platform;

class MariaDB1070PlatformTest extends MariaDB1052PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDB1070Platform();
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('UUID', $this->platform->getGuidTypeDeclarationSQL([]));
    }
}

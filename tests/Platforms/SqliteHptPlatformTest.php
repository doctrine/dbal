<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqliteHptPlatform;

class SqliteHptPlatformTest extends SqlitePlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new SqliteHptPlatform();
    }

    public function testGetDateTimeTzFormatString(): void
    {
        self::assertEquals('Y-m-d H:i:s.u', $this->platform->getDateTimeTzFormatString());
    }

    public function testGetDateTimeFormatString(): void
    {
        self::assertEquals('Y-m-d H:i:s.u', $this->platform->getDateTimeFormatString());
    }

    public function testGetTimeFormatString(): void
    {
        self::assertEquals('H:i:s.u', $this->platform->getTimeFormatString());
    }
}

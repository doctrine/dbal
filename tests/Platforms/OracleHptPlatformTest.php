<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OracleHptPlatform;

class OracleHptPlatformTest extends OraclePlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new OracleHptPlatform();
    }

    public function testGetDateTimeTzFormatString(): void
    {
        self::assertEquals('Y-m-d H:i:s.u P', $this->platform->getDateTimeTzFormatString());
    }

    public function testGetDateTimeFormatString(): void
    {
        self::assertEquals('Y-m-d H:i:s.u', $this->platform->getDateTimeFormatString());
    }
}

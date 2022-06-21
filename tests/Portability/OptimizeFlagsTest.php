<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Portability;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\OptimizeFlags;
use PHPUnit\Framework\TestCase;

class OptimizeFlagsTest extends TestCase
{
    private OptimizeFlags $optimizeFlags;

    protected function setUp(): void
    {
        $this->optimizeFlags = new OptimizeFlags();
    }

    public function testOracle(): void
    {
        $flags = ($this->optimizeFlags)(new OraclePlatform(), Connection::PORTABILITY_ALL);

        self::assertSame(0, $flags & Connection::PORTABILITY_EMPTY_TO_NULL);
    }

    public function testAnotherPlatform(): void
    {
        $flags = ($this->optimizeFlags)(new SqlitePlatform(), Connection::PORTABILITY_ALL);

        self::assertSame(Connection::PORTABILITY_ALL, $flags);
    }
}

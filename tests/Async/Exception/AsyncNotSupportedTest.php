<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Async\Exception;

use Doctrine\DBAL\Async\Exception\AsyncNotSupported;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use PHPUnit\Framework\TestCase;

use const PHP_VERSION;

class AsyncNotSupportedTest extends TestCase
{
    public function testPhpVersionTooOld(): void
    {
        $exception = AsyncNotSupported::phpVersionTooOld();

        self::assertStringContainsString('PHP 8.1 or higher', $exception->getMessage());
        self::assertStringContainsString(PHP_VERSION, $exception->getMessage());
    }

    public function testDriverNotSupported(): void
    {
        $exception = AsyncNotSupported::driverNotSupported(Driver::class);

        self::assertStringContainsString(Driver::class, $exception->getMessage());
        self::assertStringContainsString('pgsql and mysqli', $exception->getMessage());
    }

    public function testNotAllowedInTransaction(): void
    {
        $exception = AsyncNotSupported::notAllowedInTransaction();

        self::assertStringContainsString('transaction', $exception->getMessage());
        self::assertStringContainsString('separate connection', $exception->getMessage());
    }

    public function testEmptyQueryBatch(): void
    {
        $exception = AsyncNotSupported::emptyQueryBatch();

        self::assertStringContainsString('empty batch', $exception->getMessage());
    }
}


<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Logging;

use Doctrine\DBAL\Logging\LogLevelConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(LogLevelConfig::class)]
final class LogLevelConfigTest extends TestCase
{
    public function testDefault(): void
    {
        $config = new LogLevelConfig();
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_BEGIN_TRANSACTION));
        self::assertSame(LogLevel::INFO, $config->getLevel(LogLevelConfig::LOG_CONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_COMMIT));
        self::assertSame(LogLevel::INFO, $config->getLevel(LogLevelConfig::LOG_DISCONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_EXECUTE));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_QUERY));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_ROLL_BACK));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_STATEMENT));
    }

    public function testWithOverrides(): void
    {
        $config = new LogLevelConfig([
            LogLevelConfig::LOG_CONNECT => LogLevel::DEBUG,
            LogLevelConfig::LOG_ROLL_BACK => LogLevel::WARNING,
            LogLevelConfig::LOG_DISCONNECT => LogLevel::DEBUG,
        ]);
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_BEGIN_TRANSACTION));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_CONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_COMMIT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_DISCONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_EXECUTE));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_QUERY));
        self::assertSame(LogLevel::WARNING, $config->getLevel(LogLevelConfig::LOG_ROLL_BACK));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogLevelConfig::LOG_STATEMENT));
    }
}

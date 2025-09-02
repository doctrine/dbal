<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Logging;

use Doctrine\DBAL\Logging\LogLevelConfig;
use Doctrine\DBAL\Logging\LogMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(LogLevelConfig::class)]
final class LogLevelConfigTest extends TestCase
{
    public function testDefault(): void
    {
        $config = new LogLevelConfig();
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::BEGIN_TRANSACTION));
        self::assertSame(LogLevel::INFO, $config->getLevel(LogMessage::CONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::COMMIT));
        self::assertSame(LogLevel::INFO, $config->getLevel(LogMessage::DISCONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::EXECUTE));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::QUERY));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::ROLL_BACK));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::STATEMENT));
    }

    public function testWithOverrides(): void
    {
        $config = new LogLevelConfig([
            LogMessage::CONNECT->value => LogLevel::DEBUG,
            LogMessage::ROLL_BACK->value => LogLevel::WARNING,
            LogMessage::DISCONNECT->value => LogLevel::DEBUG,
        ]);
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::BEGIN_TRANSACTION));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::CONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::COMMIT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::DISCONNECT));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::EXECUTE));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::QUERY));
        self::assertSame(LogLevel::WARNING, $config->getLevel(LogMessage::ROLL_BACK));
        self::assertSame(LogLevel::DEBUG, $config->getLevel(LogMessage::STATEMENT));
    }
}

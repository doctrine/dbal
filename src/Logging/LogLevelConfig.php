<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Psr\Log\LogLevel;

final class LogLevelConfig
{
    private const DEFAULT_OPTIONS = [
        LogMessage::BEGIN_TRANSACTION->value => LogLevel::DEBUG,
        LogMessage::CONNECT->value => LogLevel::INFO,
        LogMessage::COMMIT->value => LogLevel::DEBUG,
        LogMessage::DISCONNECT->value => LogLevel::INFO,
        LogMessage::EXECUTE->value => LogLevel::DEBUG,
        LogMessage::QUERY->value => LogLevel::DEBUG,
        LogMessage::ROLL_BACK->value => LogLevel::DEBUG,
        LogMessage::STATEMENT->value => LogLevel::DEBUG,
    ];

    /** @param array<LogMessage::value, LogLevel::*> $options */
    public function __construct(private readonly array $options = [])
    {
    }

    /** @return LogLevel::* */
    public function getLevel(LogMessage $message): string
    {
        return $this->options[$message->value] ?? self::DEFAULT_OPTIONS[$message->value];
    }
}

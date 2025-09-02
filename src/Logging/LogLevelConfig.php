<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Psr\Log\LogLevel;

final class LogLevelConfig
{
    public const LOG_BEGIN_TRANSACTION = 'begin_transaction';
    public const LOG_COMMIT            = 'commit_transaction';
    public const LOG_CONNECT           = 'connect';
    public const LOG_DISCONNECT        = 'disconnect';
    public const LOG_EXECUTE           = 'execute';
    public const LOG_QUERY             = 'query';
    public const LOG_ROLL_BACK         = 'roll_back_transaction';
    public const LOG_STATEMENT         = 'statement';

    private const DEFAULT_OPTIONS = [
        self::LOG_BEGIN_TRANSACTION => LogLevel::DEBUG,
        self::LOG_CONNECT => LogLevel::INFO,
        self::LOG_COMMIT => LogLevel::DEBUG,
        self::LOG_DISCONNECT => LogLevel::INFO,
        self::LOG_EXECUTE => LogLevel::DEBUG,
        self::LOG_QUERY => LogLevel::DEBUG,
        self::LOG_ROLL_BACK => LogLevel::DEBUG,
        self::LOG_STATEMENT => LogLevel::DEBUG,
    ];

    /** @param array<self::LOG_*, LogLevel::*> $options */
    public function __construct(private readonly array $options = [])
    {
    }

    /**
     * @param self::LOG_* $message
     *
     * @return LogLevel::*
     */
    public function getLevel(string $message): string
    {
        return $this->options[$message] ?? self::DEFAULT_OPTIONS[$message];
    }
}

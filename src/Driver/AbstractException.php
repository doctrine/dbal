<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Exception as BaseException;
use Throwable;

/**
 * Abstract base implementation of the {@see DriverException} interface.
 */
abstract class AbstractException extends BaseException implements Exception
{
    /**
     * @param string         $message  The driver error message.
     * @param string|null    $sqlState The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int            $code     The driver specific error code if any.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(
        string $message,
        private readonly ?string $sqlState = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}

<?php

namespace Doctrine\DBAL\Driver;

use Exception;
use Throwable;

/**
 * A driver exception.
 *
 * Driver exceptions provide the SQLSTATE of the driver
 * and the driver specific error code at the time the error occurred.
 */
class DriverException extends Exception
{
    /**
     * The driver specific error code.
     *
     * @var int|string|null
     */
    private $errorCode;

    /**
     * The SQLSTATE of the driver.
     *
     * @var string|null
     */
    private $sqlState;

    /**
     * @param string          $message   The driver error message.
     * @param string|null     $sqlState  The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int|string|null $errorCode The driver specific error code if any.
     * @param Throwable|null  $previous  The previous throwable used for the exception chaining.
     */
    public function __construct(string $message, ?string $sqlState = null, $errorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->sqlState  = $sqlState;
    }

    /**
     * Returns the driver specific error code if available.
     *
     * Returns null if no driver specific error code is available
     * for the error raised by the driver.
     *
     * @return int|string|null The error code.
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Returns the SQLSTATE the driver was in at the time the error occurred.
     *
     * Returns null if the driver does not provide a SQLSTATE for the error occurred.
     *
     * @return string|null The SQLSTATE, or null if not available.
     */
    public function getSQLState() : ?string
    {
        return $this->sqlState;
    }
}

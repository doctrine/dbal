<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Exception;
use Throwable;

/**
 * Abstract base implementation of the {@link DriverException} interface.
 *
 * @psalm-immutable
 */
abstract class AbstractDriverException extends Exception implements DriverException
{
    /**
     * The SQLSTATE of the driver.
     *
     * @var string|null
     */
    private $sqlState;

    /**
     * @param string         $message  The driver error message.
     * @param string|null    $sqlState The SQLSTATE the driver is in at the time the error occurred, if any.
     * @param int            $code     The driver specific error code if any.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(string $message, ?string $sqlState = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->sqlState = $sqlState;
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}

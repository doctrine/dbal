<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends DBALException implements Exception
{
    /**
     * @param string    $message         The exception message.
     * @param Exception $driverException The DBAL driver exception to chain.
     */
    public function __construct(string $message, Exception $driverException)
    {
        parent::__construct($message, $driverException->getCode(), $driverException);
    }

    public function getSQLState(): ?string
    {
        $previous = $this->getPrevious();
        assert($previous instanceof Exception);

        return $previous->getSQLState();
    }
}

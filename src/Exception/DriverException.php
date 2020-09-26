<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends Exception implements Driver\Exception
{
    /**
     * @param string           $message         The exception message.
     * @param Driver\Exception $driverException The DBAL driver exception to chain.
     */
    public function __construct(string $message, Driver\Exception $driverException)
    {
        parent::__construct($message, $driverException->getCode(), $driverException);
    }

    public function getSQLState(): ?string
    {
        $previous = $this->getPrevious();
        assert($previous instanceof Driver\Exception);

        return $previous->getSQLState();
    }
}

<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Driver\Exception as TheDriverException;
use Doctrine\DBAL\Exception;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends Exception implements TheDriverException
{
    /**
     * @param string             $message         The exception message.
     * @param TheDriverException $driverException The DBAL driver exception to chain.
     */
    public function __construct($message, TheDriverException $driverException)
    {
        parent::__construct($message, $driverException->getCode(), $driverException);
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLState()
    {
        $previous = $this->getPrevious();
        assert($previous instanceof TheDriverException);

        return $previous->getSQLState();
    }
}

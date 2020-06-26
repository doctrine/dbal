<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException as DeprecatedDriverException;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends DBALException implements DeprecatedDriverException
{
    /**
     * @param string                    $message         The exception message.
     * @param DeprecatedDriverException $driverException The DBAL driver exception to chain.
     */
    public function __construct($message, DeprecatedDriverException $driverException)
    {
        parent::__construct($message, $driverException->getCode(), $driverException);
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLState()
    {
        $previous = $this->getPrevious();
        assert($previous instanceof DeprecatedDriverException);

        return $previous->getSQLState();
    }
}

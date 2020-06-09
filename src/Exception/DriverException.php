<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;

use function assert;

/**
 * Base class for all errors detected in the driver.
 *
 * @psalm-immutable
 */
class DriverException extends DBALException implements DriverExceptionInterface
{
    /**
     * @param string                   $message         The exception message.
     * @param DriverExceptionInterface $driverException The DBAL driver exception to chain.
     */
    public function __construct(string $message, DriverExceptionInterface $driverException)
    {
        parent::__construct($message, $driverException->getCode(), $driverException);
    }

    public function getSQLState(): ?string
    {
        $previous = $this->getPrevious();
        assert($previous instanceof DriverExceptionInterface);

        return $previous->getSQLState();
    }
}

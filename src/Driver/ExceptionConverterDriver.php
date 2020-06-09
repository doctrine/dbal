<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DriverException;

/**
 * Contract for a driver that is capable of converting DBAL driver exceptions into standardized DBAL driver exceptions.
 */
interface ExceptionConverterDriver
{
    /**
     * Converts a given DBAL driver exception into a standardized DBAL driver exception.
     *
     * It evaluates the vendor specific error code and SQLSTATE and transforms
     * it into a unified {@link Doctrine\DBAL\Exception\DriverException} subclass.
     *
     * @param string                   $message   The DBAL exception message to use.
     * @param DriverExceptionInterface $exception The DBAL driver exception to convert.
     *
     * @return DriverException An instance of one of the DriverException subclasses.
     */
    public function convertException(string $message, DriverExceptionInterface $exception): DriverException;
}

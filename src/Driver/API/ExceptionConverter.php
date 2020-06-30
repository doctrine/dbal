<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;

interface ExceptionConverter
{
    /**
     * Converts a given driver-level exception into a DBAL-level driver exception.
     *
     * Implementors should use the vendor-specific error code and SQLSTATE of the exception
     * and instantiate the most appropriate specialized {@link DriverException} subclass.
     *
     * @param string    $message   The exception message to use.
     * @param Exception $exception The driver exception to convert.
     *
     * @return DriverException An instance of {@link DriverException} or one of its subclasses.
     */
    public function convert(string $message, Exception $exception): DriverException;
}

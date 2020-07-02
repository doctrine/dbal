<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;

final class DefaultExceptionConverter implements ExceptionConverter
{
    public function convert(string $message, Exception $exception): DriverException
    {
        return new DriverException($message, $exception);
    }
}

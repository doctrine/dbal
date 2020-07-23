<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\SQLSrv;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\DriverException;

final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(string $message, Exception $exception): DriverException
    {
        return new DriverException($message, $exception);
    }
}

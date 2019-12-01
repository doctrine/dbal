<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Tiny wrapper for PDOException instances to implement the {@link DriverException} interface.
 */
class PDOException extends AbstractDriverException
{
    public static function fromNativePDOException(\PDOException $exception)
    {
        if ($exception->errorInfo !== null) {
            [$sqlState, $code] = $exception->errorInfo;
        } else {
            $code     = $exception->getCode();
            $sqlState = null;
        }

        return new self($exception->getMessage(), $sqlState, $code, $exception);
    }
}

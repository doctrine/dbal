<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Tiny wrapper for PDOException instances to implement the {@link DriverException} interface.
 *
 * @psalm-immutable
 */
class PDOException extends AbstractDriverException
{
    /**
     * @param \PDOException $exception The PDO exception to wrap.
     */
    public function __construct(\PDOException $exception)
    {
        if ($exception->errorInfo !== null) {
            [$sqlState, $code] = $exception->errorInfo;
        } else {
            $code     = $exception->getCode();
            $sqlState = null;
        }

        parent::__construct($exception->getMessage(), $sqlState, $code, $exception);
    }
}

<?php

namespace Doctrine\DBAL\Driver;

/**
 * @deprecated Use {@link Exception} instead
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

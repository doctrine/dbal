<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\AbstractDriverException;

/**
 * @psalm-immutable
 */
class OCI8Exception extends AbstractDriverException
{
    /**
     * @param mixed[]|false $error
     */
    public static function fromErrorInfo($error): self
    {
        if ($error === false) {
            return new self('Database error occurred but no error information was retrieved from the driver.');
        }

        return new self($error['message'], null, $error['code']);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function sprintf;

/**
 * This exception is used for any PHP error that the
 * OCI8 extension doesn't report via oci_error()
 *
 * @internal
 *
 * @psalm-immutable
 */
final class PHPError extends AbstractException
{
    public static function new(
        string $preambleMessage,
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): self {
        return new self(sprintf(
            '%s Error number: %s, Error String: %s, Error File: %s, Error Line: %s',
            $preambleMessage,
            $errno,
            $errstr,
            $errfile,
            $errline
        ));
    }
}

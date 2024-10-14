<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function assert;
use function explode;
use function oci_error;
use function str_replace;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class Error extends AbstractException
{
    private const CODE_TRANSACTION_ROLLED_BACK = 2091;

    /** @param resource $resource */
    public static function new($resource): self
    {
        $error = oci_error($resource);
        assert($error !== false);

        $code    = $error['code'];
        $message = $error['message'];
        if ($code === self::CODE_TRANSACTION_ROLLED_BACK) {
            //ORA-02091: transaction rolled back
            //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
            [, $causeError] = explode("\n", $message, 2);

            [$causeCode, $message] = explode(': ', $causeError, 2);
            $code                  = (int) str_replace('ORA-', '', $causeCode);
        }

        return new self($message, null, $code);
    }
}

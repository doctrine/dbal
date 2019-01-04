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
            // There's no way this can be unit-tested as it's impossible to mock $resource
            //ORA-02091: transaction rolled back
            //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
            [$firstMessage, $secondMessage] = explode("\n", $message, 2);

            [$code, $message] = explode(': ', $secondMessage, 2);
            $code             = (int) str_replace('ORA-', '', $code);
        }

        return new self($message, null, $code);
    }
}

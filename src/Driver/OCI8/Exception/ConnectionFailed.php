<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\OCI8\OCI8Exception;

use function assert;
use function oci_error;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class ConnectionFailed extends OCI8Exception
{
    public static function new(): self
    {
        $error = oci_error();
        assert($error !== false);

        return new self($error['message'], null, $error['code']);
    }
}

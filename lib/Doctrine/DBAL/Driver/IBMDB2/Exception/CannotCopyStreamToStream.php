<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\IBMDB2\DB2Exception;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class CannotCopyStreamToStream extends DB2Exception
{
    public static function new(string $message): self
    {
        return new self('Could not copy source stream to temporary file: ' . $message);
    }
}

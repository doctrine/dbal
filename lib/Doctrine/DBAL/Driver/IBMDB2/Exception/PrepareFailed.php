<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\IBMDB2\DB2Exception;

/**
 * @psalm-immutable
 */
final class PrepareFailed extends DB2Exception
{
    public static function new(string $message): self
    {
        return new self($message);
    }
}

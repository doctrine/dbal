<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\AbstractException;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class CannotWriteToTemporaryFile extends AbstractException
{
    public static function new(string $message): self
    {
        return new self('Could not write string to temporary file: ' . $message);
    }
}

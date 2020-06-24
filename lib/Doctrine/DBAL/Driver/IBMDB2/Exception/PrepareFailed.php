<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\IBMDB2\Exception;

use Doctrine\DBAL\Driver\AbstractException;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class PrepareFailed extends AbstractException
{
    public static function new(string $message): self
    {
        return new self($message);
    }
}

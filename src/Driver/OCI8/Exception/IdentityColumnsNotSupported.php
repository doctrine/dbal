<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8\Exception;

use Doctrine\DBAL\Driver\OCI8\OCI8Exception;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class IdentityColumnsNotSupported extends OCI8Exception
{
    public static function new(): self
    {
        return new self('The driver does not support identity columns.');
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\Driver\AbstractException;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class IdentityColumnsNotSupported extends AbstractException
{
    public static function new(): self
    {
        return new self('The driver does not support identity columns.');
    }
}

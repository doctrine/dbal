<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\Exception;

use function sprintf;

/**
 * @psalm-immutable
 */
final class NotSupported extends Exception implements PlatformException
{
    public static function new(string $method): self
    {
        return new self(sprintf('Operation "%s" is not supported by platform.', $method));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use LogicException;

use function sprintf;

final class NotSupported extends LogicException implements PlatformException
{
    public static function new(string $method): self
    {
        return new self(sprintf('Operation "%s" is not supported by platform.', $method));
    }
}

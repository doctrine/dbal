<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\DBALException;
use function sprintf;

final class NotSupported extends DBALException implements PlatformException
{
    public static function new(string $method) : self
    {
        return new self(sprintf('Operation "%s" is not supported by platform.', $method));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\ArrayParameters\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function sprintf;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class InvalidParameterType extends AbstractException
{
    public static function new(int $type): self
    {
        return new self(sprintf('Invalid parameter type, %d given.', $type));
    }
}

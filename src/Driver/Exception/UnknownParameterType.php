<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function sprintf;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class UnknownParameterType extends AbstractException
{
    public static function new(int $type): self
    {
        return new self(sprintf('Unknown parameter type, %d given.', $type));
    }
}

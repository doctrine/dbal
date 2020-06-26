<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function sprintf;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class UnknownType extends AbstractException
{
    /**
     * @param mixed $type
     */
    public static function new($type): self
    {
        return new self(sprintf('Unknown type, %d given.', $type));
    }
}

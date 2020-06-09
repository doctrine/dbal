<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\DBALException;

use function sprintf;

/**
 * @psalm-immutable
 */
final class UnknownParamType extends DBALException
{
    public static function new(int $type): self
    {
        return new self(sprintf('Unknown param type %d.', $type));
    }
}

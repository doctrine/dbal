<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\DBALException;

use function sprintf;

/**
 * @psalm-immutable
 */
final class UnknownFetchMode extends DBALException
{
    public static function new(int $fetchMode): self
    {
        return new self(sprintf('Unknown fetch mode %d.', $fetchMode));
    }
}

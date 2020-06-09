<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;

use function sprintf;

/**
 * @psalm-immutable
 */
final class InvalidDriverClass extends DBALException
{
    public static function new(string $driverClass): self
    {
        return new self(
            sprintf(
                'The given "driverClass" %s has to implement the %s interface.',
                $driverClass,
                Driver::class
            )
        );
    }
}

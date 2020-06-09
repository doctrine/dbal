<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

use function sprintf;

/**
 * @psalm-immutable
 */
final class InvalidWrapperClass extends DBALException
{
    public static function new(string $wrapperClass): self
    {
        return new self(
            sprintf(
                'The given "wrapperClass" %s has to be a subtype of %s.',
                $wrapperClass,
                Connection::class
            )
        );
    }
}

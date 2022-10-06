<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Exception;

use function sprintf;

/** @psalm-immutable */
final class TypeNotFound extends Exception implements TypesException
{
    public static function new(string $name): self
    {
        return new self(sprintf('Type to be overwritten "%s" does not exist.', $name));
    }
}

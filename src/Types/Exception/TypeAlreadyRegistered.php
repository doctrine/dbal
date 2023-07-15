<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\Type;
use Exception;

use function get_debug_type;
use function spl_object_hash;
use function sprintf;

/** @psalm-immutable */
final class TypeAlreadyRegistered extends Exception implements TypesException
{
    public static function new(Type $type): self
    {
        return new self(sprintf(
            'Type of the class %s@%s is already registered.',
            get_debug_type($type),
            spl_object_hash($type),
        ));
    }
}

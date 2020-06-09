<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;

use function get_class;
use function spl_object_hash;
use function sprintf;

/**
 * @psalm-immutable
 */
final class TypeNotRegistered extends DBALException implements TypesException
{
    public static function new(Type $type): self
    {
        return new self(sprintf('Type of the class %s@%s is not registered.', get_class($type), spl_object_hash($type)));
    }
}

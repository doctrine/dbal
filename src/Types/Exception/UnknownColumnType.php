<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Exception;

use function sprintf;

/** @psalm-immutable */
final class UnknownColumnType extends Exception implements TypesException
{
    public static function new(string $name): self
    {
        return new self(
            sprintf(
                'Unknown column type "%s" requested. Any Doctrine type that you use has '
                    . 'to be registered with \Doctrine\DBAL\Types\Type::addType(). You can get a list of all the '
                    . 'known types with \Doctrine\DBAL\Types\Type::getTypesMap(). If this error occurs during database '
                    . 'introspection then you might have forgotten to register all database types for a Doctrine Type. '
                    . 'Use AbstractPlatform#registerDoctrineTypeMapping() or have your custom types implement '
                    . 'Type#getMappedDatabaseTypes(). If the type name is empty you might '
                    . 'have a problem with the cache or forgot some mapping information.',
                $name,
            ),
        );
    }
}

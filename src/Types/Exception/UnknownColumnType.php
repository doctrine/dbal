<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Exception;
use Throwable;

use function sprintf;

final class UnknownColumnType extends Exception implements TypesException
{
    public static function new(string $name, Throwable|null $previous = null): self
    {
        return new self(
            sprintf(
                'Unknown column type "%s" requested. Register it in the TypeProvider of the connection, which is '
                    . 'configured with Configuration::setTypeProvider() and does not see types registered globally. '
                    . 'If this error occurs during database introspection then you might have forgotten to register '
                    . 'all database types for a Doctrine Type. Use '
                    . 'AbstractPlatform::registerDoctrineTypeMapping() or have your custom types implement '
                    . 'Type::getMappedDatabaseTypes(). If the type name is empty you might '
                    . 'have a problem with the cache or forgot some mapping information.',
                $name,
            ),
            0,
            $previous,
        );
    }
}

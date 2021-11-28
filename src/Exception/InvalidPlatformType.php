<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function get_debug_type;
use function is_object;
use function sprintf;

/**
 * @psalm-immutable
 */
final class InvalidPlatformType extends Exception
{
    public static function new(mixed $invalidPlatform): self
    {
        if (is_object($invalidPlatform)) {
            return new self(
                sprintf(
                    'Option "platform" must be a subtype of %s, instance of %s given.',
                    AbstractPlatform::class,
                    get_debug_type($invalidPlatform)
                )
            );
        }

        return new self(
            sprintf(
                'Option "platform" must be an object and subtype of %s. Got %s.',
                AbstractPlatform::class,
                get_debug_type($invalidPlatform)
            )
        );
    }
}

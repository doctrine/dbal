<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function get_class;
use function is_object;
use function sprintf;

/**
 * @psalm-immutable
 */
final class InvalidPlatformType extends DBALException
{
    /**
     * @param mixed $invalidPlatform
     */
    public static function new($invalidPlatform): self
    {
        if (is_object($invalidPlatform)) {
            return new self(
                sprintf(
                    'Option "platform" must be a subtype of %s, instance of %s given.',
                    AbstractPlatform::class,
                    get_class($invalidPlatform)
                )
            );
        }

        return new self(
            sprintf(
                'Option "platform" must be an object and subtype of %s. Got %s.',
                AbstractPlatform::class,
                (new GetVariableType())->__invoke($invalidPlatform)
            )
        );
    }
}

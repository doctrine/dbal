<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;

use function sprintf;

final class UnsupportedPrimaryKeyConstraintDefinition extends InvalidArgumentException implements PlatformException
{
    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromNamedConstraint(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support named primary key constraints.',
            $platformClassName,
        ));
    }

    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromNonClusteredConstraint(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s supports only clustered primary key constraints.',
            $platformClassName,
        ));
    }
}

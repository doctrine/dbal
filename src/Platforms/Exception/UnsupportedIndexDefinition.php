<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;

use function sprintf;

final class UnsupportedIndexDefinition extends InvalidArgumentException implements PlatformException
{
    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromIndexWithColumnLengths(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support indexes with column lengths.',
            $platformClassName,
        ));
    }

    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromFulltextIndex(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support fulltext indexes.',
            $platformClassName,
        ));
    }

    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromSpatialIndex(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support spatial indexes.',
            $platformClassName,
        ));
    }

    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromClusteredIndex(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support clustered indexes.',
            $platformClassName,
        ));
    }

    /** @param class-string<AbstractPlatform> $platformClassName */
    public static function fromPartialIndex(string $platformClassName): self
    {
        return new self(sprintf(
            'Database platform %s does not support partial indexes.',
            $platformClassName,
        ));
    }
}

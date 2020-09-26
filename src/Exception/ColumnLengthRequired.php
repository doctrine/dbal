<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function sprintf;

/**
 * @psalm-immutable
 */
final class ColumnLengthRequired extends Exception
{
    /**
     * @param AbstractPlatform $platform The target platform
     * @param string           $type     The SQL column type
     */
    public static function new(AbstractPlatform $platform, string $type): self
    {
        return new self(
            sprintf(
                'The "%s" platform requires the length of a %s column to be specified',
                $platform->getName(),
                $type
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function get_debug_type;
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
                '%s requires the length of a %s column to be specified',
                get_debug_type($platform),
                $type
            )
        );
    }
}

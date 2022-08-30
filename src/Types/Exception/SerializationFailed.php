<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use Throwable;

use function get_debug_type;
use function sprintf;

/** @psalm-immutable */
final class SerializationFailed extends ConversionException implements TypesException
{
    public static function new(mixed $value, string $format, string $error, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Could not convert PHP type "%s" to "%s". An error was triggered by the serialization: %s',
                get_debug_type($value),
                $format,
                $error,
            ),
            0,
            $previous,
        );
    }
}

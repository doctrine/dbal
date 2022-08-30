<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use Throwable;

use function is_string;
use function sprintf;
use function strlen;
use function substr;

/**
 * Thrown when a Database to Doctrine Type Conversion fails.
 *
 * @psalm-immutable
 */
final class ValueNotConvertible extends ConversionException implements TypesException
{
    public static function new(
        mixed $value,
        string $toType,
        ?string $message = null,
        ?Throwable $previous = null,
    ): self {
        if ($message !== null) {
            $message = sprintf(
                'Could not convert database value to "%s" as an error was triggered by the unserialization: %s',
                $toType,
                $message,
            );
        } else {
            $message = sprintf(
                'Could not convert database value "%s" to Doctrine Type "%s".',
                is_string($value) && strlen($value) > 32 ? substr($value, 0, 20) . '...' : $value,
                $toType,
            );
        }

        return new self($message, 0, $previous);
    }
}

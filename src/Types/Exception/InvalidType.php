<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use Throwable;

use function get_debug_type;
use function implode;
use function is_scalar;
use function sprintf;
use function var_export;

/**
 * Thrown when the PHP value passed to the converter was not of the expected type.
 *
 * @psalm-immutable
 */
final class InvalidType extends ConversionException implements TypesException
{
    /**
     * @param string[] $possibleTypes
     *
     * @todo split into two methods
     * @todo sanitize value
     */
    public static function new(
        mixed $value,
        string $toType,
        array $possibleTypes,
        ?Throwable $previous = null,
    ): self {
        if (is_scalar($value) || $value === null) {
            $message = sprintf(
                'Could not convert PHP value %s to type %s. Expected one of the following types: %s.',
                var_export($value, true),
                $toType,
                implode(', ', $possibleTypes),
            );
        } else {
            $message = sprintf(
                'Could not convert PHP value of type %s to type %s. Expected one of the following types: %s.',
                get_debug_type($value),
                $toType,
                implode(', ', $possibleTypes),
            );
        }

        return new self($message, 0, $previous);
    }
}

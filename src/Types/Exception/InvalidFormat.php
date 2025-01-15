<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use Throwable;

use function sprintf;
use function strlen;
use function substr;

/**
 * Thrown when a Database to Doctrine Type Conversion fails and we can make a statement
 * about the expected format.
 */
final class InvalidFormat extends ConversionException implements TypesException
{
    public static function new(
        string $value,
        string $toType,
        ?string $expectedFormat,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                'Could not convert database value "%s" to Doctrine Type %s. Expected format "%s".',
                strlen($value) > 32 ? substr($value, 0, 20) . '...' : $value,
                $toType,
                $expectedFormat ?? '',
            ),
            0,
            $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use function sprintf;
use function strlen;
use function substr;

/**
 * Thrown when a Database to Doctrine Type Conversion fails.
 */
final class ValueNotConvertible extends ConversionException implements TypesException
{
    public static function new(string $value, string $toType) : self
    {
        return new self(
            sprintf(
                'Could not convert database value "%s" to Doctrine Type %s',
                strlen($value) > 32 ? substr($value, 0, 20) . '...' : $value,
                $toType
            )
        );
    }
}

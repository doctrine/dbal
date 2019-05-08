<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use function get_class;
use function gettype;
use function implode;
use function is_object;
use function is_scalar;
use function sprintf;

/**
 * Thrown when the PHP value passed to the converter was not of the expected type.
 */
final class InvalidType extends ConversionException implements TypesException
{
    /**
     * @param mixed    $value
     * @param string[] $possibleTypes
     *
     * @todo split into two methods
     * @todo sanitize value
     */
    public static function new($value, string $toType, array $possibleTypes) : self
    {
        $actualType = is_object($value) ? get_class($value) : gettype($value);

        if (is_scalar($value)) {
            return new self(
                sprintf(
                    'Could not convert PHP value "%s" of type "%s" to type "%s". Expected one of the following types: %s.',
                    $value,
                    $actualType,
                    $toType,
                    implode(', ', $possibleTypes)
                )
            );
        }

        return new self(
            sprintf(
                'Could not convert PHP value of type "%s" to type "%s". Expected one of the following types: %s.',
                $actualType,
                $toType,
                implode(', ', $possibleTypes)
            )
        );
    }
}

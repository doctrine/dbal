<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Doctrine\DBAL\Types\ConversionException;
use function get_class;
use function gettype;
use function is_object;
use function sprintf;

final class SerializationFailed extends ConversionException implements TypesException
{
    /**
     * @param mixed $value
     */
    public static function new($value, string $format, string $error) : self
    {
        $actualType = is_object($value) ? get_class($value) : gettype($value);

        return new self(
            sprintf(
                'Could not convert PHP type "%s" to "%s". An error was triggered by the serialization: %s',
                $actualType,
                $format,
                $error
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL VARCHAR to a PHP non-empty-string.
 *
 * This type ensures that empty strings are rejected during conversion,
 * providing stronger type safety for fields that must contain a value.
 */
class NonEmptyStringType extends StringType
{
    /**
     * {@inheritDoc}
     *
     * @param non-empty-string $value
     *
     * @return non-empty-string
     *
     * @throws ConversionException If the value is not a non-empty string.
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if (!\is_string($value) || '' === $value) {
            throw ConversionException::conversionFailed($value, 'non-empty-string');
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @param non-empty-string $value
     *
     * @return non-empty-string
     *
     * @throws ConversionException If the value is not a non-empty string.
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): string
    {
        if (!\is_string($value) || '' === $value) {
            throw ConversionException::conversionFailed($value, 'non-empty-string');
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return Types::NON_EMPTY_STRING;
    }
}

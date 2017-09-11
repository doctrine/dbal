<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL DATE to a PHP Date object.
 *
 * @since 2.0
 */
class DateType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::DATE;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getDateTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($platform->getDateFormatString());
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'DateTime']);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof \DateTimeInterface) {
            return $value;
        }

        $val = \DateTime::createFromFormat('!'.$platform->getDateFormatString(), $value);
        if ( ! $val) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), $platform->getDateFormatString());
        }

        return $val;
    }
}

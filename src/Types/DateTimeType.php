<?php

namespace Doctrine\DBAL\Types;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function date_create;

/**
 * Type that maps an SQL DATETIME/TIMESTAMP to a PHP DateTime object.
 */
class DateTimeType extends Type implements PhpDateTimeMappingType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::DATETIME_MUTABLE;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($platform->getDateTimeFormatString());
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            static::class,
            ['null', 'DateTime']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }

        $val = DateTime::createFromFormat($platform->getDateTimeFormatString(), $value);

        if ($val === false) {
            $val = date_create($value);
        }

        if ($val === false) {
            throw ConversionException::conversionFailedFormat(
                $value,
                static::class,
                $platform->getDateTimeFormatString()
            );
        }

        return $val;
    }
}

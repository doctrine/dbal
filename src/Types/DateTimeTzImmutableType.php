<?php

namespace Doctrine\DBAL\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Immutable type of {@see DateTimeTzType}.
 */
class DateTimeTzImmutableType extends DateTimeTzType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::DATETIMETZ_IMMUTABLE;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format($platform->getDateTimeTzFormatString());
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            static::class,
            ['null', DateTimeImmutable::class]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        $dateTime = DateTimeImmutable::createFromFormat($platform->getDateTimeTzFormatString(), $value);

        if ($dateTime === false) {
            throw ConversionException::conversionFailedFormat(
                $value,
                static::class,
                $platform->getDateTimeTzFormatString()
            );
        }

        return $dateTime;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}

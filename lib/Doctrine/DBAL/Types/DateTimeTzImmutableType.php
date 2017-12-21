<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Immutable type of {@see DateTimeTzType}.
 *
 * @since  2.6
 * @author Steve Müller <deeky666@googlemail.com>
 */
class DateTimeTzImmutableType extends DateTimeTzType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::DATETIMETZ_IMMUTABLE;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format($platform->getDateTimeTzFormatString());
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
            ['null', \DateTimeImmutable::class]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat($platform->getDateTimeTzFormatString(), $value);

        if (! $dateTime) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
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

<?php

namespace Doctrine\DBAL\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

/**
 * Immutable type of {@see DateType}.
 */
class DateImmutableType extends DateType
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return Types::DATE_IMMUTABLE;
    }

    /**
     * {@inheritDoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : string)
     *
     * @template T
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            $offset        = $value->format('O');
            $defaultOffset = (new DateTimeImmutable())->format('O');

            if ($offset !== $defaultOffset) {
                Deprecation::triggerIfCalledFromOutside(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6020',
                    'Passing a timezone offset (%s) different than the default one (%s) is deprecated'
                    . ' as it will be lost, use %s::%s() instead.',
                    $offset,
                    $defaultOffset,
                    DateTimeTzImmutableType::class,
                    __FUNCTION__,
                );
            }

            return $value->format($platform->getDateFormatString());
        }

        throw ConversionException::conversionFailedInvalidType(
            $value,
            $this->getName(),
            ['null', DateTimeImmutable::class],
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : DateTimeImmutable)
     *
     * @template T
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            $offset        = $value->format('O');
            $defaultOffset = (new DateTimeImmutable())->format('O');

            if ($offset !== $defaultOffset) {
                Deprecation::triggerIfCalledFromOutside(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6020',
                    'Passing a timezone offset (%s) different than the default one (%s) is deprecated'
                    . ' as it may be lost, use %s::%s() instead.',
                    $offset,
                    $defaultOffset,
                    DateTimeTzImmutableType::class,
                    __FUNCTION__,
                );
            }

            return $value;
        }

        $dateTime = DateTimeImmutable::createFromFormat('!' . $platform->getDateFormatString(), $value);

        if ($dateTime === false) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getDateFormatString(),
            );
        }

        return $dateTime;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5509',
            '%s is deprecated.',
            __METHOD__,
        );

        return true;
    }
}

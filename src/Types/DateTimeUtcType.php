<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\Deprecations\Deprecation;
use Throwable;

/**
 * Mutable variant of {@see DateTimeUtcImmutableType}.
 *
 * @deprecated Use {@see DateTimeUtcImmutableType} instead.
 */
class DateTimeUtcType extends Type implements PhpDateTimeMappingType
{
    private static ?DateTimeZone $utc = null;

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $this->triggerDeprecation();

        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : string)
     *
     * @template T
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $this->triggerDeprecation();

        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return (clone $value)->setTimezone(self::getUtc())
                ->format($platform->getDateTimeFormatString());
        }

        throw InvalidType::new(
            $value,
            static::class,
            ['null', DateTime::class],
        );
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : DateTime)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        $this->triggerDeprecation();

        if ($value === null || $value instanceof DateTime) {
            return $value;
        }

        $dateTime = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::getUtc(),
        );

        if ($dateTime !== false) {
            return $dateTime;
        }

        try {
            return new DateTime($value, self::getUtc());
        } catch (Throwable $e) {
            throw InvalidFormat::new(
                $value,
                static::class,
                $platform->getDateTimeFormatString(),
                $e,
            );
        }
    }

    private static function getUtc(): DateTimeZone
    {
        return self::$utc ??= new DateTimeZone('UTC');
    }

    private function triggerDeprecation(): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7575',
            'The "%s" type is deprecated. Use the "%s" type instead.',
            self::class,
            DateTimeUtcImmutableType::class,
        );
    }
}

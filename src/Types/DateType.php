<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\Deprecations\Deprecation;

/**
 * Mutable variant of {@see DateImmutableType}.
 *
 * @deprecated Use {@see DateImmutableType} instead.
 */
class DateType extends Type implements PhpDateMappingType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $this->triggerDeprecation();

        return $platform->getDateTypeDeclarationSQL($column);
    }

    /**
     * @phpstan-param T $value
     *
     * @return (T is null ? null : string)
     *
     * @template T
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $this->triggerDeprecation();

        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return $value->format($platform->getDateFormatString());
        }

        throw InvalidType::new($value, static::class, ['null', DateTime::class]);
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

        $dateTime = DateTime::createFromFormat('!' . $platform->getDateFormatString(), $value);
        if ($dateTime !== false) {
            return $dateTime;
        }

        throw InvalidFormat::new(
            $value,
            static::class,
            $platform->getDateFormatString(),
        );
    }

    private function triggerDeprecation(): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/7575',
            'The "%s" type is deprecated. Use the "%s" type instead.',
            self::class,
            DateImmutableType::class,
        );
    }
}

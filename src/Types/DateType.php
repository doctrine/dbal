<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * Type that maps an SQL DATE to a PHP Date object.
 */
class DateType extends Type implements PhpDateMappingType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
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
}

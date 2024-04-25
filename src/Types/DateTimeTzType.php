<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * DateTime type accepting additional information about timezone offsets.
 *
 * Caution: Databases are not necessarily experts at storing timezone related
 * data of dates. First, of not all the supported vendors support storing Timezone data, and some of
 * them only use the offset to calculate the timestamp in its default timezone (usually UTC) and persist
 * the value without the offset information. They even don't save the actual timezone names attached
 * to a DateTime instance (for example "Europe/Berlin" or "America/Montreal") but the current offset
 * of them related to UTC. That means, depending on daylight saving times or not, you may get different
 * offsets.
 *
 * This datatype makes only sense to use, if your application only needs to accept the timezone offset,
 * not the actual timezone that uses transitions. Otherwise your DateTime instance
 * attached with a timezone such as "Europe/Berlin" gets saved into the database with
 * the offset and re-created from persistence with only the offset, not the original timezone
 * attached.
 */
class DateTimeTzType extends Type implements PhpDateTimeMappingType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTzTypeDeclarationSQL($column);
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
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTime) {
            return $value->format($platform->getDateTimeTzFormatString());
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
        if ($value === null || $value instanceof DateTime) {
            return $value;
        }

        $dateTime = DateTime::createFromFormat($platform->getDateTimeTzFormatString(), $value);
        if ($dateTime !== false) {
            return $dateTime;
        }

        throw InvalidFormat::new(
            $value,
            static::class,
            $platform->getDateTimeTzFormatString(),
        );
    }
}

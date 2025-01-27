<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Exception;

/**
 * @deprecated Use {@see DateTimeType} instead.
 */
class VarDateTimeType extends DateTimeType
{
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

        try {
            $dateTime = new DateTime($value);
        } catch (Exception $e) {
            throw ValueNotConvertible::new($value, DateTime::class, $e->getMessage(), $e);
        }

        return $dateTime;
    }
}

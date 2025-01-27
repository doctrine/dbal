<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Exception;

/**
 * @deprecated Use {@see DateTimeImmutableType} instead.
 */
class VarDateTimeImmutableType extends DateTimeImmutableType
{
    /**
     * @param T $value
     *
     * @return (T is null ? null : DateTimeImmutable)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            $dateTime = new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw ValueNotConvertible::new($value, DateTimeImmutable::class, $e->getMessage(), $e);
        }

        return $dateTime;
    }
}

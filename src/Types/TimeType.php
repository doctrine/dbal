<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * Type that maps an SQL TIME to a PHP DateTime object.
 */
class TimeType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getTimeTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($platform->getTimeFormatString());
        }

        throw InvalidType::new($value, static::class, ['null', 'DateTime']);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeInterface
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }

        $val = DateTime::createFromFormat('!' . $platform->getTimeFormatString(), $value);
        if ($val === false) {
            throw InvalidFormat::new(
                $value,
                static::class,
                $platform->getTimeFormatString()
            );
        }

        return $val;
    }
}

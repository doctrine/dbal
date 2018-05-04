<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * Immutable type of {@see DateType}.
 */
class DateImmutableType extends DateType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::DATE_IMMUTABLE;
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
            return $value->format($platform->getDateFormatString());
        }

        throw InvalidType::new(
            $value,
            $this->getName(),
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

        $dateTime = DateTimeImmutable::createFromFormat('!' . $platform->getDateFormatString(), $value);

        if (! $dateTime) {
            throw InvalidFormat::new(
                $value,
                $this->getName(),
                $platform->getDateFormatString()
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

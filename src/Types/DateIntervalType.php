<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use DateInterval;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Throwable;

use function substr;

/**
 * Type that maps interval string to a PHP DateInterval Object.
 */
class DateIntervalType extends Type
{
    public const FORMAT = '%RP%YY%MM%DDT%HH%IM%SS';

    public function getName(): string
    {
        return Types::DATEINTERVAL;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        $fieldDeclaration['length'] = 255;

        return $platform->getStringTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateInterval) {
            return $value->format(self::FORMAT);
        }

        throw InvalidType::new($value, $this->getName(), ['null', 'DateInterval']);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null || $value instanceof DateInterval) {
            return $value;
        }

        $negative = false;

        if (isset($value[0]) && ($value[0] === '+' || $value[0] === '-')) {
            $negative = $value[0] === '-';
            $value    = substr($value, 1);
        }

        try {
            $interval = new DateInterval($value);

            if ($negative) {
                $interval->invert = 1;
            }

            return $interval;
        } catch (Throwable $exception) {
            throw InvalidFormat::new($value, $this->getName(), self::FORMAT, $exception);
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

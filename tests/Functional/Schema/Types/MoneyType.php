<?php

namespace Doctrine\DBAL\Tests\Functional\Schema\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

use function is_string;

class MoneyType extends Type
{
    public const NAME = 'money';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return $value;
        }

        if ($value instanceof Money) {
            return $value->__toString();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', Money::class]);
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?Money
    {
        if ($value === null || $value instanceof Money) {
            return $value;
        }

        if (! is_string($value)) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'string']);
        }

        try {
            return new Money($value);
        } catch (InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), Money::class, $e);
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

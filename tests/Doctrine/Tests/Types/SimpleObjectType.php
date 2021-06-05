<?php
declare(strict_types=1);

namespace Doctrine\Tests\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;
use Doctrine\Tests\Object\SimpleObject;

class SimpleObjectType extends StringType
{
    public const SIMPLE_OBJECT = 'simple_object';

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return self::SIMPLE_OBJECT;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?SimpleObject
    {
        if ($value instanceof SimpleObject || null === $value) {
            return $value;
        }

        if (!\is_string($value)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                $this->getName(),
                ['null', 'string', SimpleObject::class]
            );
        }

        return new SimpleObject($value);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof SimpleObject) {
            return $value->getString();
        }

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ConversionException::conversionFailedInvalidType(
                $value,
                $this->getName(),
                ['null', 'string', SimpleObject::class]
            );
        }

        return $value;
    }
}

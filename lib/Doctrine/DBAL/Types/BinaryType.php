<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use function is_resource;
use function is_string;
use function stream_get_contents;

/**
 * Type that maps ab SQL BINARY/VARBINARY to a PHP resource stream.
 */
class BinaryType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getBinaryTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (! is_string($value)) {
            throw ConversionException::conversionFailed($value, self::BINARY);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::BINARY;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        return ParameterType::BINARY;
    }
}

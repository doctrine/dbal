<?php

namespace Doctrine\DBAL\Types;

/**
 * Type that maps a PHP object to a clob SQL type.
 *
 * @since 2.0
 */
class ObjectType extends Type
{
    public function getSQLDeclaration(array $fieldDeclaration, \Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    public function convertToDatabaseValue($value, \Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        return serialize($value);
    }

    public function convertToPHPValue($value, \Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        $value = (is_resource($value)) ? stream_get_contents($value) : $value;
        $val = unserialize($value);
        if ($val === false) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
        return $val;
    }

    public function getName()
    {
        return Type::OBJECT;
    }
}
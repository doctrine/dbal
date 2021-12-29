<?php

namespace Doctrine\DBAL\Types;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps a PHP enum to a SQL string type.
 */
class EnumType extends PolymorphicType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BackedEnum
    {
        if (empty($value)) {
            return null;
        }

        $enum = $this->getName();

        if (enum_exists($enum)) {
            return $enum::from($value);
        }

        return null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return null;
    }
}

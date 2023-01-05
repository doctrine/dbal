<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class FloatType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Types::FLOAT;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getFloatDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : float)
     *
     * @template T
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value === null ? null : (float) $value;
    }
}

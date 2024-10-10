<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class SmallFloatType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallFloatDeclarationSQL($column);
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : float)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

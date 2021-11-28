<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class FloatType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getFloatDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

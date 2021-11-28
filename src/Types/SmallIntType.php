<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps a database SMALLINT to a PHP integer.
 */
class SmallIntType extends Type implements PhpIntegerMappingType
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function getBindingType(): int
    {
        return ParameterType::INTEGER;
    }
}

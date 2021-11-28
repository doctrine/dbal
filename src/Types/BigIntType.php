<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps a database BIGINT to a PHP string.
 */
class BigIntType extends Type implements PhpIntegerMappingType
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    public function getBindingType(): int
    {
        return ParameterType::STRING;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

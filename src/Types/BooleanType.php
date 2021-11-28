<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;

/**
 * Type that maps an SQL boolean to a PHP boolean.
 */
class BooleanType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBooleanTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $platform->convertBooleansToDatabaseValue($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?bool
    {
        return $platform->convertFromBoolean($value);
    }

    public function getBindingType(): int
    {
        return ParameterType::BOOLEAN;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        // We require a commented boolean type in order to distinguish between
        // boolean and smallint as both (have to) map to the same native type.
        return $platform instanceof DB2Platform;
    }
}

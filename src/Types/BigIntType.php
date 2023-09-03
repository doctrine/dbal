<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Type that maps a database BIGINT to a PHP int.
 */
class BigIntType extends Type implements PhpIntegerMappingType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : int|string)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): int|string|null
    {
        if ($value === null) {
            return null;
        }

        if (
            $value >= PHP_INT_MIN &&
            $value <= PHP_INT_MAX
        ) {
            return (int) $value;
        }

        return (string) $value;
    }
}

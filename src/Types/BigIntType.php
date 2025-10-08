<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function assert;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Type that attempts to map a database BIGINT to a PHP int.
 *
 * If the presented value is outside of PHP's integer range, the value is returned as-is (usually a string).
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
        return ParameterType::STRING;
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
        if ($value === null || is_int($value)) {
            return $value;
        }

        assert(
            is_string($value) && is_numeric($value),
            'DBAL assumes values outside of the integer range to be returned as string by the database driver.',
        );

        $intValue = 0 + $value;
        if (is_int($intValue)) {
            return $intValue;
        }

        return $value;
    }
}

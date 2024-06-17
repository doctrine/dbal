<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function assert;
use function is_int;
use function is_string;
use function str_starts_with;
use function substr;

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
            is_string($value),
            'DBAL assumes values outside of the integer range to be returned as string by the database driver.',
        );

        if (str_starts_with($value, '+') || $value === '-0') {
            $value = substr($value, 1);
        }

        if ($value === (string) (int) $value) {
            return (int) $value;
        }

        return $value;
    }
}

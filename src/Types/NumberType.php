<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use BcMath\Number;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use TypeError;
use ValueError;

use function is_float;
use function is_int;
use function is_string;

final class NumberType extends Type
{
    /** {@inheritDoc} */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Number) {
            throw InvalidType::new($value, static::class, ['null', Number::class]);
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Number
    {
        if ($value === null) {
            return null;
        }

        // SQLite might return a decimal as float.
        if (is_float($value)) {
            $value = (string) $value;
        }

        if (! is_int($value) && ! is_string($value)) {
            throw InvalidType::new($value, 'int|string', ['int', 'string']);
        }

        try {
            return new Number($value);
        } catch (TypeError | ValueError $e) {
            throw ValueNotConvertible::new(value: $value, toType: static::class, message: null, previous: $e);
        }
    }
}

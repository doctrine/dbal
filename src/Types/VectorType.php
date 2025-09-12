<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;

use function array_values;
use function is_array;
use function pack;
use function unpack;

final class VectorType extends Type
{
    /** @inheritdoc */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVectorTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw InvalidType::new(
                $value,
                static::class,
                ['null', 'array'],
            );
        }

        return pack('f*', ...$value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        return array_values(unpack('f*', $value));
    }
}

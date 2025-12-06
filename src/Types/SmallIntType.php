<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * Type that maps a database SMALLINT to a PHP integer.
 */
class SmallIntType extends Type implements PhpIntegerMappingType
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : int)
     *
     * @template T
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw InvalidType::new($value, 'int', ['scalar']);
        }

        return (int) $value;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }
}

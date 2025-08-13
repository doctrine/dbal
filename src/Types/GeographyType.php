<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_string;

/**
 * Type that maps a database GEOGRAPHY column to a PHP string containing GeoJSON data.
 *
 * Geography types handle spherical coordinate systems and are typically used for
 * earth-based geographic data with latitude/longitude coordinates.
 * This is distinct from geometry types which use planar/Euclidean coordinate systems.
 */
class GeographyType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGeographyTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        throw ValueNotConvertible::new($value, Types::GEOGRAPHY);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        throw ValueNotConvertible::new($value, Types::GEOGRAPHY);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->getGeographyFromGeoJSONSQL($sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->getGeographyAsGeoJSONSQL($sqlExpr);
    }
}

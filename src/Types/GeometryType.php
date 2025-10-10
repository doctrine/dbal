<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_string;

/**
 * Type that maps a database GEOMETRY column to a PHP Geometry value object.
 *
 * This type handles geometric data stored in various database formats and converts
 * it to/from a Geometry value object that encapsulates GeoJSON.
 */
class GeometryType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGeometryTypeDeclarationSQL($column);
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

        if ($value instanceof Geometry) {
            return $value->toGeoJSON();
        }

        throw ValueNotConvertible::new($value, Types::GEOMETRY);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Geometry|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return Geometry::fromGeoJSON($value);
        }

        throw ValueNotConvertible::new($value, Types::GEOMETRY);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->getGeometryFromGeoJSONSQL($sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->getGeometryAsGeoJSONSQL($sqlExpr);
    }
}

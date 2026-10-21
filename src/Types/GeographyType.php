<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_resource;
use function stream_get_contents;

/**
 * Maps a spatial column whose coordinates are longitudes and latitudes to a {@see Geometry} value object.
 *
 * PostgreSQL and SQL Server declare a geography column, whose functions compute on the ellipsoid. The other platforms
 * declare a geometry column with the SRID 4326. The values without SRID are given the SRID 4326 (WGS 84).
 */
final class GeographyType extends Type implements PlatformAwareBindingType
{
    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGeographyTypeDeclarationSQL($column);
    }

    /** @throws Exception */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Geometry) {
            throw InvalidType::new($value, static::class, ['null', Geometry::class]);
        }

        if ($value->getSrid() === 0) {
            $value = $value->withSrid(4326);
        }

        try {
            return $platform->convertGeographyToDatabaseValue($value);
        } catch (InvalidGeometry $e) {
            throw SerializationFailed::new($value, Types::GEOGRAPHY, $e->getMessage(), $e);
        }
    }

    /** @throws Exception */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Geometry
    {
        if ($value === null || $value instanceof Geometry) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        try {
            return $platform->convertGeographyToPHPValue($value);
        } catch (InvalidGeometry $e) {
            throw ValueNotConvertible::new($value, Types::GEOGRAPHY, $e->getMessage(), $e);
        }
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->convertGeographyToDatabaseValueSQL($sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->convertGeographyToPHPValueSQL($sqlExpr);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    public function getBindingTypeForPlatform(AbstractPlatform $platform): ParameterType
    {
        return $platform->getGeographyBindingType();
    }
}

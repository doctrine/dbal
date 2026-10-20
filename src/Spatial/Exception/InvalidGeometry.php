<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Exception;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use Throwable;

use function sprintf;

/**
 * Thrown when a geometry is malformed, or cannot be represented in the requested format.
 */
final class InvalidGeometry extends InvalidArgumentException
{
    public static function unknownGeometryKind(string $name): self
    {
        return new self(sprintf('Unknown geometry type "%s".', $name));
    }

    public static function unknownDimension(string $name): self
    {
        return new self(sprintf('Unknown coordinate dimension "%s", expected XY, XYZ, XYM or XYZM.', $name));
    }

    public static function unsupportedWkbType(int $code): self
    {
        return new self(sprintf(
            'Unsupported WKB geometry type %d: only the OGC Simple Features types (no curves) are supported.',
            $code,
        ));
    }

    public static function invalidSrid(int $srid): self
    {
        return new self(sprintf('Invalid SRID %d: SRIDs must be between 0 and %d.', $srid, Geometry::MAX_SRID));
    }

    public static function conflictingSrid(int $embedded, int $given): self
    {
        return new self(sprintf(
            'The geometry carries the SRID %d, which conflicts with the SRID %d.',
            $embedded,
            $given,
        ));
    }

    public static function malformedWkb(string $reason): self
    {
        return new self(sprintf('Malformed WKB: %s.', $reason));
    }

    public static function malformedSpatiaLiteBlob(string $reason): self
    {
        return new self(sprintf('Malformed SpatiaLite geometry: %s.', $reason));
    }

    public static function malformedSQLServerGeometry(string $reason): self
    {
        return new self(sprintf('Malformed SQL Server geometry: %s.', $reason));
    }

    public static function malformedWkt(string $reason, int $offset): self
    {
        return new self(sprintf('Malformed WKT at offset %d: %s.', $offset, $reason));
    }

    public static function malformedGeoJson(string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('Malformed GeoJSON: %s.', $reason), 0, $previous);
    }

    public static function invalidStructure(GeometryKind $kind, string $reason): self
    {
        return new self(sprintf('Invalid %s: %s.', $kind->value, $reason));
    }

    public static function mixedDimensions(Dimension $expected, Dimension $actual): self
    {
        return new self(sprintf(
            'Mixed coordinate dimensions: expected %s, got %s.',
            $expected->value,
            $actual->value,
        ));
    }

    public static function unsupportedDimension(Dimension $dimension, string $format): self
    {
        return new self(sprintf('%s does not support geometries with %s coordinates.', $format, $dimension->value));
    }

    public static function unsupportedEmptyGeometry(string $platform): self
    {
        return new self(sprintf('%s do not support empty geometries, except empty geometry collections.', $platform));
    }

    public static function unsupportedKind(string $platform, GeometryKind $kind): self
    {
        return new self(sprintf('%s does not support %s values.', $platform, $kind->value));
    }

    public static function unsupportedEmptyGeometries(string $platform): self
    {
        return new self(sprintf('%s does not support empty geometries.', $platform));
    }

    public static function unsupportedNestedCollection(string $platform, GeometryKind $kind): self
    {
        return new self(sprintf('%s does not support geometry collections containing a %s.', $platform, $kind->value));
    }

    public static function unsupportedCoordinate(string $platform, float $coordinate): self
    {
        return new self(sprintf('%s cannot store the coordinate %s.', $platform, $coordinate));
    }

    public static function unsupportedCurves(string $platform): self
    {
        return new self(sprintf('Curves stored in %s are not supported.', $platform));
    }

    public static function malformedSdoGeometry(string $reason): self
    {
        return new self(sprintf('Malformed SDO_GEOMETRY: %s.', $reason));
    }

    public static function unsupportedEmptyPoint(string $platform): self
    {
        return new self(sprintf('%s does not support empty points.', $platform));
    }

    public static function notACollection(GeometryKind $kind): self
    {
        return new self(sprintf('A %s is not a geometry collection.', $kind->value));
    }

    public static function isACollection(): self
    {
        return new self('A GEOMETRYCOLLECTION has no coordinates, use getGeometries() instead.');
    }
}

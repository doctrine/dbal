<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

use Doctrine\DBAL\Spatial\Codec\GeoJsonReader;
use Doctrine\DBAL\Spatial\Codec\GeoJsonWriter;
use Doctrine\DBAL\Spatial\Codec\WkbReader;
use Doctrine\DBAL\Spatial\Codec\WkbWriter;
use Doctrine\DBAL\Spatial\Codec\WktReader;
use Doctrine\DBAL\Spatial\Codec\WktWriter;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;

use function array_is_list;
use function count;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function sprintf;

/**
 * An immutable geometry of the OGC Simple Features model (points, line strings, polygons and their
 * collections, without curves) with its spatial reference system identifier (SRID).
 *
 * An SRID of 0 means that the spatial reference system is not specified. Coordinates are in the axis order
 * of the well-known text and binary formats: X (longitude) first, then Y (latitude), optionally followed by
 * Z and M.
 *
 * The coordinates of a geometry are nested lists of positions, a position being a list of 2 to 4 floats:
 * a position for a POINT (an empty list for an empty point), a list of positions for a LINESTRING, a list
 * of rings (lists of positions) for a POLYGON, and a list of the coordinates of their members for the
 * MULTI* kinds. A GEOMETRYCOLLECTION has no coordinates but members, see {@see getGeometries()}.
 */
final class Geometry
{
    /**
     * The highest SRID that every supported database platform can store.
     */
    public const MAX_SRID = 999_999;

    private ?string $wkb = null;

    /** @param list<mixed> $coordinates */
    private function __construct(
        private readonly GeometryKind $kind,
        private readonly Dimension $dimension,
        private readonly array $coordinates,
        private readonly int $srid,
    ) {
    }

    /**
     * Creates a geometry from its well-known binary representation.
     *
     * Both byte orders and the extended format of PostGIS (EWKB) are accepted. If the value carries an SRID,
     * it is used and the given SRID must be either 0 or the same.
     *
     * @throws InvalidGeometry
     */
    public static function fromWkb(string $wkb, int $srid = 0): self
    {
        return WkbReader::read($wkb, $srid);
    }

    /**
     * Creates a geometry from its well-known text representation.
     *
     * The extended format of PostGIS (EWKT, e.g. "SRID=4326;POINT(2.35 48.85)") is accepted. If the value
     * carries an SRID, it is used and the given SRID must be either 0 or the same.
     *
     * @throws InvalidGeometry
     */
    public static function fromWkt(string $wkt, int $srid = 0): self
    {
        return WktReader::read($wkt, $srid);
    }

    /**
     * Creates a geometry from a GeoJSON geometry object (RFC 7946).
     *
     * GeoJSON coordinates are WGS 84 longitudes and latitudes, hence the default SRID 4326.
     *
     * @throws InvalidGeometry
     */
    public static function fromGeoJson(string $geoJson, int $srid = 4326): self
    {
        return GeoJsonReader::read($geoJson, $srid);
    }

    /**
     * Creates a two-dimensional point, e.g. Geometry::point($longitude, $latitude, 4326).
     *
     * @throws InvalidGeometry
     */
    public static function point(float $x, float $y, int $srid = 0): self
    {
        return self::create(GeometryKind::POINT, Dimension::XY, [$x, $y], $srid);
    }

    /**
     * Creates a geometry from its parts, validating them.
     *
     * @internal
     *
     * @param list<mixed> $coordinates The coordinates, or the members of a GEOMETRYCOLLECTION.
     *
     * @throws InvalidGeometry
     */
    public static function create(GeometryKind $kind, Dimension $dimension, array $coordinates, int $srid): self
    {
        if ($srid < 0 || $srid > self::MAX_SRID) {
            throw InvalidGeometry::invalidSrid($srid);
        }

        if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
            $members = [];

            foreach ($coordinates as $member) {
                if (! $member instanceof self) {
                    throw InvalidGeometry::invalidStructure($kind, 'its members must be geometries');
                }

                if ($member->dimension !== $dimension) {
                    throw InvalidGeometry::mixedDimensions($dimension, $member->dimension);
                }

                $members[] = $member->srid === $srid ? $member : $member->withSrid($srid);
            }

            return new self($kind, $dimension, $members, $srid);
        }

        match ($kind) {
            GeometryKind::POINT => self::validatePoint($coordinates, $dimension, $kind),
            GeometryKind::LINESTRING => self::validateLineString($coordinates, $dimension, $kind),
            GeometryKind::POLYGON => self::validatePolygon($coordinates, $dimension, $kind),
            GeometryKind::MULTIPOINT,
            GeometryKind::MULTILINESTRING,
            GeometryKind::MULTIPOLYGON => self::validateMembers($coordinates, $dimension, $kind),
            GeometryKind::GEOMETRY => throw InvalidGeometry::invalidStructure(
                $kind,
                'a geometry value must have a concrete type',
            ),
        };

        return new self($kind, $dimension, $coordinates, $srid);
    }

    public function getKind(): GeometryKind
    {
        return $this->kind;
    }

    public function getDimension(): Dimension
    {
        return $this->dimension;
    }

    public function getSrid(): int
    {
        return $this->srid;
    }

    public function isEmpty(): bool
    {
        return $this->coordinates === [];
    }

    /**
     * Returns the coordinates of the geometry, see the class description for their structure.
     *
     * @return list<mixed>
     *
     * @throws InvalidGeometry If the geometry is a GEOMETRYCOLLECTION.
     */
    public function getCoordinates(): array
    {
        if ($this->kind === GeometryKind::GEOMETRYCOLLECTION) {
            throw InvalidGeometry::isACollection();
        }

        return $this->coordinates;
    }

    /**
     * Returns the members of a MULTI* geometry or of a GEOMETRYCOLLECTION.
     *
     * @return list<self>
     *
     * @throws InvalidGeometry If the geometry is not a collection.
     */
    public function getGeometries(): array
    {
        if ($this->kind === GeometryKind::GEOMETRYCOLLECTION) {
            /** @var list<self> $members */
            $members = $this->coordinates;

            return $members;
        }

        $memberKind = $this->kind->getMemberKind() ?? throw InvalidGeometry::notACollection($this->kind);

        $members = [];

        foreach ($this->coordinates as $coordinates) {
            /** @var list<mixed> $coordinates */
            $members[] = new self($memberKind, $this->dimension, $coordinates, $this->srid);
        }

        return $members;
    }

    /**
     * Returns a copy of the geometry with the given SRID. The coordinates are not transformed.
     *
     * @throws InvalidGeometry
     */
    public function withSrid(int $srid): self
    {
        if ($srid === $this->srid) {
            return $this;
        }

        if ($srid < 0 || $srid > self::MAX_SRID) {
            throw InvalidGeometry::invalidSrid($srid);
        }

        if ($this->kind !== GeometryKind::GEOMETRYCOLLECTION) {
            return new self($this->kind, $this->dimension, $this->coordinates, $srid);
        }

        $members = [];

        foreach ($this->getGeometries() as $member) {
            $members[] = $member->withSrid($srid);
        }

        return new self($this->kind, $this->dimension, $members, $srid);
    }

    /**
     * Returns the ISO well-known binary representation of the geometry, in little-endian byte order. It does
     * not carry the SRID.
     */
    public function asWkb(): string
    {
        return $this->wkb ??= WkbWriter::write($this);
    }

    /**
     * Returns the extended well-known binary representation (EWKB) of the geometry, in little-endian byte
     * order. It carries the SRID unless it is 0.
     */
    public function asEwkb(): string
    {
        return WkbWriter::writeExtended($this);
    }

    /**
     * Returns the ISO well-known text representation of the geometry. It does not carry the SRID.
     */
    public function asWkt(): string
    {
        return WktWriter::write($this, false);
    }

    /**
     * Returns the extended well-known text representation (EWKT) of the geometry, e.g.
     * "SRID=4326;POINT(2.35 48.85)". It carries the SRID unless it is 0.
     */
    public function asEwkt(): string
    {
        return WktWriter::write($this, true);
    }

    /**
     * Returns the GeoJSON geometry object (RFC 7946) of the geometry. It does not carry the SRID.
     *
     * @throws InvalidGeometry If the geometry has M coordinates, which GeoJSON cannot represent.
     */
    public function asGeoJson(): string
    {
        return GeoJsonWriter::write($this);
    }

    /**
     * Returns whether the geometries have the same SRID and are made of exactly the same coordinates in the
     * same order. This is not a spatial equality test.
     */
    public function equals(self $other): bool
    {
        return $this->srid === $other->srid && $this->asWkb() === $other->asWkb();
    }

    /** @return array{srid: int, wkb: string} */
    public function __serialize(): array
    {
        return ['srid' => $this->srid, 'wkb' => $this->asWkb()];
    }

    /**
     * @param array{srid: int, wkb: string} $data
     *
     * @throws InvalidGeometry
     */
    public function __unserialize(array $data): void
    {
        $geometry = WkbReader::read($data['wkb'], $data['srid']);

        $this->kind        = $geometry->kind;
        $this->dimension   = $geometry->dimension;
        $this->coordinates = $geometry->coordinates;
        $this->srid        = $geometry->srid;
    }

    /**
     * @param array<mixed> $coordinates
     *
     * @throws InvalidGeometry
     */
    private static function validatePoint(array $coordinates, Dimension $dimension, GeometryKind $kind): void
    {
        if ($coordinates === []) {
            return;
        }

        self::validatePosition($coordinates, $dimension, $kind);
    }

    /**
     * @param array<mixed> $coordinates
     *
     * @throws InvalidGeometry
     */
    private static function validateLineString(array $coordinates, Dimension $dimension, GeometryKind $kind): void
    {
        if (! array_is_list($coordinates)) {
            throw InvalidGeometry::invalidStructure($kind, 'its coordinates must be a list of positions');
        }

        if (count($coordinates) === 1) {
            throw InvalidGeometry::invalidStructure($kind, 'a line string must have either 0 or at least 2 points');
        }

        foreach ($coordinates as $position) {
            self::validatePosition($position, $dimension, $kind);
        }
    }

    /**
     * @param array<mixed> $coordinates
     *
     * @throws InvalidGeometry
     */
    private static function validatePolygon(array $coordinates, Dimension $dimension, GeometryKind $kind): void
    {
        if (! array_is_list($coordinates)) {
            throw InvalidGeometry::invalidStructure($kind, 'its coordinates must be a list of rings');
        }

        foreach ($coordinates as $ring) {
            if (! is_array($ring) || ! array_is_list($ring) || count($ring) < 4) {
                throw InvalidGeometry::invalidStructure($kind, 'a ring must have at least 4 points');
            }

            foreach ($ring as $position) {
                self::validatePosition($position, $dimension, $kind);
            }

            if ($ring[0] !== $ring[count($ring) - 1]) {
                throw InvalidGeometry::invalidStructure($kind, 'a ring must be closed');
            }
        }
    }

    /**
     * @param array<mixed> $coordinates
     *
     * @throws InvalidGeometry
     */
    private static function validateMembers(array $coordinates, Dimension $dimension, GeometryKind $kind): void
    {
        if (! array_is_list($coordinates)) {
            throw InvalidGeometry::invalidStructure($kind, 'its coordinates must be a list of members');
        }

        foreach ($coordinates as $member) {
            if (! is_array($member)) {
                throw InvalidGeometry::invalidStructure($kind, 'its members must be lists of coordinates');
            }

            match ($kind) {
                GeometryKind::MULTIPOINT => self::validatePoint($member, $dimension, $kind),
                GeometryKind::MULTILINESTRING => self::validateLineString($member, $dimension, $kind),
                default => self::validatePolygon($member, $dimension, $kind),
            };
        }
    }

    /** @throws InvalidGeometry */
    private static function validatePosition(mixed $position, Dimension $dimension, GeometryKind $kind): void
    {
        $count = $dimension->getCoordinateCount();

        if (! is_array($position) || ! array_is_list($position) || count($position) !== $count) {
            throw InvalidGeometry::invalidStructure(
                $kind,
                sprintf('a position must be a list of %d coordinates (%s)', $count, $dimension->value),
            );
        }

        foreach ($position as $coordinate) {
            if (is_int($coordinate) || ! is_float($coordinate) || ! is_finite($coordinate)) {
                throw InvalidGeometry::invalidStructure($kind, 'coordinates must be finite floats');
            }
        }
    }
}

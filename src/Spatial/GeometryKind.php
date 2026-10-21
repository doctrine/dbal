<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;

use function strtoupper;
use function trim;

/**
 * The geometry types of the OGC Simple Features specification, and the curves and surfaces of ISO 13249-3 (SQL/MM),
 * which only PostgreSQL supports.
 *
 * {@see GEOMETRY} designates any geometry. It is used to declare unconstrained columns and is never the kind of a
 * {@see Geometry} value.
 */
enum GeometryKind: string
{
    case GEOMETRY           = 'GEOMETRY';
    case POINT              = 'POINT';
    case LINESTRING         = 'LINESTRING';
    case POLYGON            = 'POLYGON';
    case MULTIPOINT         = 'MULTIPOINT';
    case MULTILINESTRING    = 'MULTILINESTRING';
    case MULTIPOLYGON       = 'MULTIPOLYGON';
    case GEOMETRYCOLLECTION = 'GEOMETRYCOLLECTION';
    case CIRCULARSTRING     = 'CIRCULARSTRING';
    case COMPOUNDCURVE      = 'COMPOUNDCURVE';
    case CURVEPOLYGON       = 'CURVEPOLYGON';
    case MULTICURVE         = 'MULTICURVE';
    case MULTISURFACE       = 'MULTISURFACE';
    case POLYHEDRALSURFACE  = 'POLYHEDRALSURFACE';
    case TIN                = 'TIN';
    case TRIANGLE           = 'TRIANGLE';

    /**
     * Returns the kind for the given name, ignoring case. "GEOMCOLLECTION" is accepted as an alias of
     * "GEOMETRYCOLLECTION".
     *
     * @throws InvalidGeometry
     */
    public static function fromName(string $name): self
    {
        $name = strtoupper(trim($name));

        if ($name === 'GEOMCOLLECTION') {
            return self::GEOMETRYCOLLECTION;
        }

        return self::tryFrom($name) ?? throw InvalidGeometry::unknownGeometryKind($name);
    }

    /**
     * Returns the kind for the given WKB type code (without dimension information).
     *
     * @throws InvalidGeometry
     */
    public static function fromWkbCode(int $code): self
    {
        return match ($code) {
            0 => self::GEOMETRY,
            1 => self::POINT,
            2 => self::LINESTRING,
            3 => self::POLYGON,
            4 => self::MULTIPOINT,
            5 => self::MULTILINESTRING,
            6 => self::MULTIPOLYGON,
            7 => self::GEOMETRYCOLLECTION,
            8 => self::CIRCULARSTRING,
            9 => self::COMPOUNDCURVE,
            10 => self::CURVEPOLYGON,
            11 => self::MULTICURVE,
            12 => self::MULTISURFACE,
            15 => self::POLYHEDRALSURFACE,
            16 => self::TIN,
            17 => self::TRIANGLE,
            default => throw InvalidGeometry::unsupportedWkbType($code),
        };
    }

    /**
     * Returns the WKB type code of the kind (0 for {@see GEOMETRY}).
     */
    public function getWkbCode(): int
    {
        return match ($this) {
            self::GEOMETRY => 0,
            self::POINT => 1,
            self::LINESTRING => 2,
            self::POLYGON => 3,
            self::MULTIPOINT => 4,
            self::MULTILINESTRING => 5,
            self::MULTIPOLYGON => 6,
            self::GEOMETRYCOLLECTION => 7,
            self::CIRCULARSTRING => 8,
            self::COMPOUNDCURVE => 9,
            self::CURVEPOLYGON => 10,
            self::MULTICURVE => 11,
            self::MULTISURFACE => 12,
            self::POLYHEDRALSURFACE => 15,
            self::TIN => 16,
            self::TRIANGLE => 17,
        };
    }

    /**
     * Returns the kind of the members of a homogeneous collection, NULL for other kinds.
     */
    public function getMemberKind(): ?self
    {
        return match ($this) {
            self::MULTIPOINT => self::POINT,
            self::MULTILINESTRING => self::LINESTRING,
            self::MULTIPOLYGON, self::POLYHEDRALSURFACE => self::POLYGON,
            self::TIN => self::TRIANGLE,
            default => null,
        };
    }

    /**
     * Returns whether the values of the kind are made of member geometries, which can be of several kinds, rather than
     * of coordinates.
     */
    public function hasGeometryMembers(): bool
    {
        return match ($this) {
            self::GEOMETRYCOLLECTION,
            self::COMPOUNDCURVE,
            self::CURVEPOLYGON,
            self::MULTICURVE,
            self::MULTISURFACE => true,
            default => false,
        };
    }

    /**
     * Returns whether a value of this kind accepts members of the given kind, for the kinds having geometry members.
     */
    public function acceptsMember(self $kind): bool
    {
        return match ($this) {
            self::GEOMETRYCOLLECTION => $kind !== self::GEOMETRY,
            self::COMPOUNDCURVE => $kind === self::LINESTRING || $kind === self::CIRCULARSTRING,
            self::CURVEPOLYGON,
            self::MULTICURVE => $kind === self::LINESTRING || $kind === self::CIRCULARSTRING
                || $kind === self::COMPOUNDCURVE,
            self::MULTISURFACE => $kind === self::POLYGON || $kind === self::CURVEPOLYGON,
            default => false,
        };
    }

    /**
     * Returns whether the kind is a curve or a surface of ISO 13249-3 (SQL/MM), which is not part of the OGC Simple
     * Features specification.
     */
    public function isExtended(): bool
    {
        return $this->getWkbCode() > 7;
    }

    /**
     * Returns whether a column declared with this kind accepts values of the given kind.
     */
    public function accepts(self $kind): bool
    {
        return $this === self::GEOMETRY || $this === $kind;
    }
}

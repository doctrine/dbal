<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;

use function strtoupper;
use function trim;

/**
 * The OGC Simple Features geometry types.
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
     * Returns the kind for the given WKB type code (1 to 7, without dimension information).
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
            self::MULTIPOLYGON => self::POLYGON,
            default => null,
        };
    }

    /**
     * Returns whether a column declared with this kind accepts values of the given kind.
     */
    public function accepts(self $kind): bool
    {
        return $this === self::GEOMETRY || $this === $kind;
    }
}

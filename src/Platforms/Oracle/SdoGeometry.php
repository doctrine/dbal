<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Oracle;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;
use JsonException;

use function abs;
use function array_chunk;
use function array_merge;
use function array_reverse;
use function array_slice;
use function count;
use function in_array;
use function ini_get;
use function ini_set;
use function intdiv;
use function is_array;
use function is_float;
use function is_int;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Converts geometries to and from the components of the SDO_GEOMETRY type of Oracle (SDO_GTYPE, SDO_SRID,
 * SDO_POINT, SDO_ELEM_INFO and SDO_ORDINATES), serialized as a JSON object.
 *
 * The SQL expressions of {@see OraclePlatform} build an SDO_GEOMETRY object from this JSON object and back with
 * the SQL/JSON functions, which are lossless, unlike the WKT and WKB functions of Oracle Spatial.
 *
 * @internal
 *
 * @link https://docs.oracle.com/en/database/oracle/oracle-database/19/spatl/spatial-datatypes-metadata.html
 */
final class SdoGeometry
{
    private const ETYPE_POINT              = 1;
    private const ETYPE_LINE               = 2;
    private const ETYPE_EXTERIOR           = 1003;
    private const ETYPE_INTERIOR           = 2003;
    private const INTERPRETATION_RECTANGLE = 3;

    /** The kinds of the collection members that Oracle collections cannot represent */
    private const NESTED_COLLECTIONS = [
        GeometryKind::MULTILINESTRING,
        GeometryKind::MULTIPOLYGON,
        GeometryKind::GEOMETRYCOLLECTION,
    ];

    /** The largest absolute value that the NUMBER type of Oracle can store (exclusive) */
    private const MAX_ORDINATE = 1e125;

    /** The smallest non-zero absolute value that the NUMBER type of Oracle can store */
    private const MIN_ORDINATE = 1e-129;

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }

    /**
     * Returns the JSON object {"t": SDO_GTYPE, "s": SDO_SRID, "e": SDO_ELEM_INFO, "o": SDO_ORDINATES}.
     *
     * Polygon rings are oriented as Oracle requires: counterclockwise for the exterior rings, clockwise for the
     * interior rings.
     *
     * @throws InvalidGeometry
     */
    public static function encode(Geometry $geometry): string
    {
        if (self::containsEmptyGeometry($geometry)) {
            throw InvalidGeometry::unsupportedEmptyGeometries('Oracle');
        }

        $elementInfo = $ordinates = [];

        if ($geometry->getKind() === GeometryKind::GEOMETRYCOLLECTION) {
            foreach ($geometry->getGeometries() as $member) {
                if (in_array($member->getKind(), self::NESTED_COLLECTIONS, true)) {
                    throw InvalidGeometry::unsupportedNestedCollection('Oracle', $member->getKind());
                }

                self::addElements($member, $elementInfo, $ordinates);
            }
        } else {
            self::addElements($geometry, $elementInfo, $ordinates);
        }

        foreach ($ordinates as $ordinate) {
            $absolute = abs($ordinate);

            if ($absolute >= self::MAX_ORDINATE || ($absolute !== 0.0 && $absolute < self::MIN_ORDINATE)) {
                throw InvalidGeometry::unsupportedCoordinate('Oracle', $ordinate);
            }
        }

        $data = [
            't' => self::getGType($geometry),
            's' => $geometry->getSrid() === 0 ? null : $geometry->getSrid(),
            'e' => $elementInfo,
            'o' => $ordinates,
        ];

        // the shortest representation of the floats that reads back as the same float
        $precision = ini_get('serialize_precision');

        if ($precision !== '-1') {
            ini_set('serialize_precision', '-1');
        }

        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidGeometry::malformedSdoGeometry($e->getMessage());
        } finally {
            if ($precision !== '-1' && $precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }
    }

    /**
     * Converts the JSON object {"t": SDO_GTYPE, "s": SDO_SRID, "p": SDO_POINT, "e": SDO_ELEM_INFO,
     * "o": SDO_ORDINATES} to a geometry.
     *
     * @throws InvalidGeometry
     */
    public static function decode(string $json): Geometry
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidGeometry::malformedSdoGeometry($e->getMessage());
        }

        if (! is_array($data) || ! is_int($data['t'] ?? null)) {
            throw InvalidGeometry::malformedSdoGeometry('missing SDO_GTYPE');
        }

        $gtype     = $data['t'];
        $dimension = self::getDimension($gtype);
        $type      = $gtype % 100;
        $srid      = $data['s'] ?? 0;

        if (! is_int($srid)) {
            throw InvalidGeometry::malformedSdoGeometry('invalid SDO_SRID');
        }

        $count = $dimension->getCoordinateCount();

        if (($data['e'] ?? null) === null) {
            // a point stored in the SDO_POINT attribute
            $point = self::toFloats($data['p'] ?? null);

            if ($type !== 1 || count($point) !== 3 || $count > 3) {
                throw InvalidGeometry::malformedSdoGeometry('missing SDO_ELEM_INFO');
            }

            return Geometry::create(GeometryKind::POINT, $dimension, array_slice($point, 0, $count), $srid);
        }

        $elementInfo = $data['e'];
        $ordinates   = self::toFloats($data['o'] ?? null);

        if (! is_array($elementInfo) || count($elementInfo) % 3 !== 0) {
            throw InvalidGeometry::malformedSdoGeometry('invalid SDO_ELEM_INFO');
        }

        $members = self::readElements($elementInfo, $ordinates, $dimension, $srid);

        $kind = match ($type) {
            1 => GeometryKind::POINT,
            2 => GeometryKind::LINESTRING,
            3 => GeometryKind::POLYGON,
            4 => GeometryKind::GEOMETRYCOLLECTION,
            5 => GeometryKind::MULTIPOINT,
            6 => GeometryKind::MULTILINESTRING,
            7 => GeometryKind::MULTIPOLYGON,
            default => throw InvalidGeometry::malformedSdoGeometry(sprintf('unsupported SDO_GTYPE %d', $gtype)),
        };

        if ($kind === GeometryKind::GEOMETRYCOLLECTION) {
            return Geometry::create($kind, $dimension, $members, $srid);
        }

        $memberKind = $kind->getMemberKind();

        if ($memberKind === null) {
            if (count($members) !== 1 || $members[0]->getKind() !== $kind) {
                throw InvalidGeometry::malformedSdoGeometry(sprintf('unexpected elements for a %s', $kind->value));
            }

            return $members[0];
        }

        $coordinates = [];

        foreach ($members as $member) {
            if ($member->getKind() === $kind) {
                // a point cluster
                $coordinates = array_merge($coordinates, $member->getCoordinates());

                continue;
            }

            if ($member->getKind() !== $memberKind) {
                throw InvalidGeometry::malformedSdoGeometry(sprintf('unexpected elements for a %s', $kind->value));
            }

            $coordinates[] = $member->getCoordinates();
        }

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /**
     * @param list<int>   $elementInfo
     * @param list<float> $ordinates
     */
    private static function addElements(Geometry $geometry, array &$elementInfo, array &$ordinates): void
    {
        $kind = $geometry->getKind();

        switch ($kind) {
            case GeometryKind::POINT:
                $elementInfo = [...$elementInfo, count($ordinates) + 1, self::ETYPE_POINT, 1];
                /** @var list<float> $position */
                $position  = $geometry->getCoordinates();
                $ordinates = [...$ordinates, ...$position];

                return;

            case GeometryKind::MULTIPOINT:
                /** @var list<list<float>> $positions */
                $positions   = $geometry->getCoordinates();
                $elementInfo = [...$elementInfo, count($ordinates) + 1, self::ETYPE_POINT, count($positions)];
                $ordinates   = [...$ordinates, ...array_merge(...$positions)];

                return;

            case GeometryKind::LINESTRING:
                /** @var list<list<float>> $positions */
                $positions   = $geometry->getCoordinates();
                $elementInfo = [...$elementInfo, count($ordinates) + 1, self::ETYPE_LINE, 1];
                $ordinates   = [...$ordinates, ...array_merge(...$positions)];

                return;

            case GeometryKind::POLYGON:
                /** @var list<list<list<float>>> $rings */
                $rings = $geometry->getCoordinates();

                foreach ($rings as $i => $ring) {
                    $exterior = $i === 0;

                    // the shoelace formula is positive for counterclockwise rings
                    if ((self::getSignedArea($ring) > 0) !== $exterior) {
                        $ring = array_reverse($ring);
                    }

                    $elementInfo = [
                        ...$elementInfo,
                        count($ordinates) + 1,
                        $exterior ? self::ETYPE_EXTERIOR : self::ETYPE_INTERIOR,
                        1,
                    ];
                    $ordinates   = [...$ordinates, ...array_merge(...$ring)];
                }

                return;

            default:
                foreach ($geometry->getGeometries() as $member) {
                    self::addElements($member, $elementInfo, $ordinates);
                }
        }
    }

    /** @param list<list<float>> $ring */
    private static function getSignedArea(array $ring): float
    {
        $area = 0.0;

        for ($i = 0, $count = count($ring) - 1; $i < $count; $i++) {
            $area += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
        }

        return $area;
    }

    /**
     * @param array<mixed> $elementInfo
     * @param list<float>  $ordinates
     *
     * @return list<Geometry>
     *
     * @throws InvalidGeometry
     */
    private static function readElements(array $elementInfo, array $ordinates, Dimension $dimension, int $srid): array
    {
        $count    = $dimension->getCoordinateCount();
        $triplets = array_chunk($elementInfo, 3);
        $members  = [];

        /** @var list<list<list<float>>>|null $polygon */
        $polygon = null;

        foreach ($triplets as $i => [$offset, $etype, $interpretation]) {
            if (! is_int($offset) || ! is_int($etype) || ! is_int($interpretation)) {
                throw InvalidGeometry::malformedSdoGeometry('invalid SDO_ELEM_INFO');
            }

            $end       = isset($triplets[$i + 1]) ? $triplets[$i + 1][0] : count($ordinates) + 1;
            $positions = array_chunk(array_slice($ordinates, $offset - 1, $end - $offset), $count);

            if ($etype !== self::ETYPE_INTERIOR && $polygon !== null) {
                $members[] = Geometry::create(GeometryKind::POLYGON, $dimension, $polygon, $srid);
                $polygon   = null;
            }

            switch ($etype) {
                case self::ETYPE_POINT:
                    if ($interpretation < 1 || count($positions) !== $interpretation) {
                        throw InvalidGeometry::malformedSdoGeometry('unsupported point element');
                    }

                    $members[] = $interpretation === 1
                        ? Geometry::create(GeometryKind::POINT, $dimension, $positions[0], $srid)
                        : Geometry::create(GeometryKind::MULTIPOINT, $dimension, $positions, $srid);
                    break;

                case self::ETYPE_LINE:
                    if ($interpretation !== 1) {
                        throw InvalidGeometry::unsupportedCurves('Oracle');
                    }

                    $members[] = Geometry::create(GeometryKind::LINESTRING, $dimension, $positions, $srid);
                    break;

                case self::ETYPE_EXTERIOR:
                case self::ETYPE_INTERIOR:
                    if ($interpretation === self::INTERPRETATION_RECTANGLE && count($positions) === 2) {
                        [[$minX, $minY], [$maxX, $maxY]] = $positions;

                        $positions = [[$minX, $minY], [$maxX, $minY], [$maxX, $maxY], [$minX, $maxY], [$minX, $minY]];
                    } elseif ($interpretation !== 1) {
                        throw InvalidGeometry::unsupportedCurves('Oracle');
                    }

                    if ($etype === self::ETYPE_EXTERIOR) {
                        $polygon = [$positions];
                    } elseif ($polygon === null) {
                        throw InvalidGeometry::malformedSdoGeometry('interior ring without exterior ring');
                    } else {
                        $polygon[] = $positions;
                    }

                    break;

                default:
                    throw InvalidGeometry::malformedSdoGeometry(sprintf('unsupported element type %d', $etype));
            }
        }

        if ($polygon !== null) {
            $members[] = Geometry::create(GeometryKind::POLYGON, $dimension, $polygon, $srid);
        }

        return $members;
    }

    private static function getGType(Geometry $geometry): int
    {
        $dimension = $geometry->getDimension();

        $type = match ($geometry->getKind()) {
            GeometryKind::POINT => 1,
            GeometryKind::LINESTRING => 2,
            GeometryKind::POLYGON => 3,
            GeometryKind::GEOMETRYCOLLECTION, GeometryKind::GEOMETRY => 4,
            GeometryKind::MULTIPOINT => 5,
            GeometryKind::MULTILINESTRING => 6,
            GeometryKind::MULTIPOLYGON => 7,
        };

        // the position of the measure, if any
        $measure = match ($dimension) {
            Dimension::XYM => 3,
            Dimension::XYZM => 4,
            default => 0,
        };

        return $dimension->getCoordinateCount() * 1000 + $measure * 100 + $type;
    }

    /** @throws InvalidGeometry */
    private static function getDimension(int $gtype): Dimension
    {
        $count   = intdiv($gtype, 1000);
        $measure = intdiv($gtype % 1000, 100);

        return match (true) {
            $count === 2 && $measure === 0 => Dimension::XY,
            $count === 3 && $measure === 0 => Dimension::XYZ,
            $count === 3 && $measure === 3 => Dimension::XYM,
            $count === 4 && ($measure === 0 || $measure === 4) => Dimension::XYZM,
            default => throw InvalidGeometry::malformedSdoGeometry(sprintf('unsupported SDO_GTYPE %d', $gtype)),
        };
    }

    private static function containsEmptyGeometry(Geometry $geometry): bool
    {
        if ($geometry->isEmpty()) {
            return true;
        }

        $kind = $geometry->getKind();

        if ($kind !== GeometryKind::GEOMETRYCOLLECTION && $kind->getMemberKind() === null) {
            return false;
        }

        foreach ($geometry->getGeometries() as $member) {
            if (self::containsEmptyGeometry($member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private static function toFloats(mixed $values): array
    {
        if (! is_array($values)) {
            throw InvalidGeometry::malformedSdoGeometry('missing ordinates');
        }

        $floats = [];

        foreach ($values as $value) {
            if ($value === null && count($floats) === 2) {
                // the Z ordinate of a two-dimensional SDO_POINT
                $floats[] = 0.0;

                continue;
            }

            if (! is_int($value) && ! is_float($value)) {
                throw InvalidGeometry::malformedSdoGeometry('ordinates must be numbers');
            }

            $floats[] = (float) $value;
        }

        return $floats;
    }
}

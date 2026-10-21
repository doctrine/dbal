<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial\Codec;

use Doctrine\DBAL\Spatial\Dimension;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Spatial\GeometryKind;

use function count;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strtoupper;
use function substr;
use function trim;

/**
 * Reads the ISO and extended (PostGIS) well-known text formats, including the curves and surfaces of ISO 13249-3
 * (SQL/MM).
 *
 * @internal
 */
final class WktReader
{
    private const WORD   = 0;
    private const NUMBER = 1;
    private const SYMBOL = 2;

    private const DIMENSION_TAGS = ['ZM' => Dimension::XYZM, 'Z' => Dimension::XYZ, 'M' => Dimension::XYM];

    private const TOKEN_PATTERN = '/\G\s*(?:([A-Za-z]+)|([-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?)|([(),]))/';

    /** @var list<array{self::WORD|self::NUMBER|self::SYMBOL, string, int}> */
    private array $tokens = [];

    private int $current = 0;

    /** @throws InvalidGeometry */
    private function __construct(private readonly string $wkt, int $offset)
    {
        $length = strlen($wkt);

        while ($offset < $length && preg_match(self::TOKEN_PATTERN, $wkt, $matches, 0, $offset) === 1) {
            if (isset($matches[3])) {
                $this->tokens[] = [self::SYMBOL, $matches[3], $offset];
            } elseif (isset($matches[2]) && $matches[2] !== '') {
                $this->tokens[] = [self::NUMBER, $matches[2], $offset];
            } else {
                $this->tokens[] = [self::WORD, strtoupper($matches[1]), $offset];
            }

            $offset += strlen($matches[0]);
        }

        if (trim(substr($wkt, $offset)) !== '') {
            throw InvalidGeometry::malformedWkt('unexpected character', $offset);
        }
    }

    /**
     * @param int $srid The SRID of the geometry, unless the value carries one.
     *
     * @throws InvalidGeometry
     */
    public static function read(string $wkt, int $srid = 0): Geometry
    {
        $offset = 0;

        if (preg_match('/^\s*SRID\s*=\s*(-?\d+)\s*;/i', $wkt, $matches) === 1) {
            $embeddedSrid = (int) $matches[1];
            $offset       = strlen($matches[0]);

            if ($embeddedSrid > 0) {
                if ($srid !== 0 && $embeddedSrid !== $srid) {
                    throw InvalidGeometry::conflictingSrid($embeddedSrid, $srid);
                }

                $srid = $embeddedSrid;
            }
        }

        $reader   = new self($wkt, $offset);
        $geometry = $reader->readGeometry(null, $srid);

        if ($reader->current < count($reader->tokens)) {
            throw InvalidGeometry::malformedWkt('unexpected token', $reader->tokens[$reader->current][2]);
        }

        return $geometry;
    }

    /** @throws InvalidGeometry */
    private function readGeometry(?Dimension $parentDimension, int $srid): Geometry
    {
        [$kind, $declaredDimension] = $this->readTag();

        if ($this->acceptWord('EMPTY')) {
            $dimension = $declaredDimension ?? $parentDimension ?? Dimension::XY;
            $this->checkParentDimension($parentDimension, $dimension);

            return Geometry::create($kind, $dimension, [], $srid);
        }

        if ($kind->hasGeometryMembers()) {
            $dimension = $declaredDimension ?? $parentDimension;
            $members   = [];

            $this->expectSymbol('(');

            do {
                // the line strings of curves and the polygons of multi-surfaces are written without their type
                $member = $kind !== GeometryKind::GEOMETRYCOLLECTION && $this->isSymbol('(')
                    ? $this->readUntaggedMember($kind, $dimension, $srid)
                    : $this->readGeometry($dimension, $srid);

                $dimension ??= $member->getDimension();
                $members[]   = $member;
            } while ($this->acceptSymbol(','));

            $this->expectSymbol(')');

            $this->checkParentDimension($parentDimension, $dimension);

            return Geometry::create($kind, $dimension, $members, $srid);
        }

        $coordinates = match ($kind) {
            GeometryKind::POINT => $this->readPointText(),
            GeometryKind::LINESTRING, GeometryKind::CIRCULARSTRING => $this->readPositionList(),
            GeometryKind::POLYGON, GeometryKind::TRIANGLE => $this->readRingList(),
            GeometryKind::MULTIPOINT => $this->readMultiPointText(),
            GeometryKind::MULTILINESTRING => $this->readMultiLineStringText(),
            default => $this->readMultiPolygonText(),
        };

        $dimension = $this->resolveDimension(
            self::getFirstPositionLength($kind, $coordinates),
            $declaredDimension,
            $parentDimension,
        );

        $this->checkParentDimension($parentDimension, $dimension);

        return Geometry::create($kind, $dimension, $coordinates, $srid);
    }

    /** @throws InvalidGeometry */
    private function readUntaggedMember(GeometryKind $kind, ?Dimension $dimension, int $srid): Geometry
    {
        if ($kind === GeometryKind::MULTISURFACE) {
            $memberKind  = GeometryKind::POLYGON;
            $coordinates = $this->readRingList();
        } else {
            $memberKind  = GeometryKind::LINESTRING;
            $coordinates = $this->readPositionList();
        }

        return Geometry::create(
            $memberKind,
            $this->resolveDimension(self::getFirstPositionLength($memberKind, $coordinates), null, $dimension),
            $coordinates,
            $srid,
        );
    }

    /**
     * @return array{GeometryKind, ?Dimension}
     *
     * @throws InvalidGeometry
     */
    private function readTag(): array
    {
        [$type, $word, $offset] = $this->next();

        if ($type !== self::WORD) {
            throw InvalidGeometry::malformedWkt('expected a geometry type', $offset);
        }

        $dimension = null;

        foreach (self::DIMENSION_TAGS as $suffix => $suffixDimension) {
            if (GeometryKind::tryFrom($word) !== null || ! str_ends_with($word, $suffix)) {
                continue;
            }

            $word      = substr($word, 0, -strlen($suffix));
            $dimension = $suffixDimension;

            break;
        }

        $kind = GeometryKind::tryFrom($word);

        if ($kind === null || $kind === GeometryKind::GEOMETRY) {
            throw InvalidGeometry::malformedWkt(sprintf('unknown geometry type "%s"', $word), $offset);
        }

        if ($dimension === null) {
            foreach (self::DIMENSION_TAGS as $tag => $tagDimension) {
                if ($this->acceptWord($tag)) {
                    $dimension = $tagDimension;

                    break;
                }
            }
        }

        return [$kind, $dimension];
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readPointText(): array
    {
        $this->expectSymbol('(');
        $position = $this->readPosition();
        $this->expectSymbol(')');

        return $position;
    }

    /**
     * @return list<list<float>>
     *
     * @throws InvalidGeometry
     */
    private function readPositionList(): array
    {
        $positions = [];

        $this->expectSymbol('(');

        do {
            $positions[] = $this->readPosition();
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(')');

        return $positions;
    }

    /**
     * @return list<list<list<float>>>
     *
     * @throws InvalidGeometry
     */
    private function readRingList(): array
    {
        $rings = [];

        $this->expectSymbol('(');

        do {
            $rings[] = $this->readPositionList();
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(')');

        return $rings;
    }

    /**
     * @return list<list<float>>
     *
     * @throws InvalidGeometry
     */
    private function readMultiPointText(): array
    {
        $points = [];

        $this->expectSymbol('(');

        do {
            if ($this->acceptWord('EMPTY')) {
                $points[] = [];
            } elseif ($this->acceptSymbol('(')) {
                $points[] = $this->readPosition();
                $this->expectSymbol(')');
            } else {
                // the parentheses around the points of a multi-point are optional
                $points[] = $this->readPosition();
            }
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(')');

        return $points;
    }

    /**
     * @return list<list<list<float>>>
     *
     * @throws InvalidGeometry
     */
    private function readMultiLineStringText(): array
    {
        $lineStrings = [];

        $this->expectSymbol('(');

        do {
            $lineStrings[] = $this->acceptWord('EMPTY') ? [] : $this->readPositionList();
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(')');

        return $lineStrings;
    }

    /**
     * @return list<list<list<list<float>>>>
     *
     * @throws InvalidGeometry
     */
    private function readMultiPolygonText(): array
    {
        $polygons = [];

        $this->expectSymbol('(');

        do {
            $polygons[] = $this->acceptWord('EMPTY') ? [] : $this->readRingList();
        } while ($this->acceptSymbol(','));

        $this->expectSymbol(')');

        return $polygons;
    }

    /**
     * @return list<float>
     *
     * @throws InvalidGeometry
     */
    private function readPosition(): array
    {
        $position = [];

        while (isset($this->tokens[$this->current]) && $this->tokens[$this->current][0] === self::NUMBER) {
            $position[] = (float) $this->tokens[$this->current][1];
            $this->current++;
        }

        $count = count($position);

        if ($count < 2 || $count > 4) {
            throw InvalidGeometry::malformedWkt(
                sprintf('a position must have 2 to 4 coordinates, got %d', $count),
                $this->getOffset(),
            );
        }

        return $position;
    }

    /** @throws InvalidGeometry */
    private function resolveDimension(?int $length, ?Dimension $declared, ?Dimension $parent): Dimension
    {
        if ($declared !== null) {
            if ($length !== null && $length !== $declared->getCoordinateCount()) {
                throw InvalidGeometry::malformedWkt(sprintf(
                    'expected %d coordinates per position (%s), got %d',
                    $declared->getCoordinateCount(),
                    $declared->value,
                    $length,
                ), $this->getOffset());
            }

            return $declared;
        }

        if ($length === null) {
            return $parent ?? Dimension::XY;
        }

        if ($parent !== null && $parent->getCoordinateCount() === $length) {
            return $parent;
        }

        return match ($length) {
            2 => Dimension::XY,
            3 => Dimension::XYZ,
            default => Dimension::XYZM,
        };
    }

    /** @throws InvalidGeometry */
    private function checkParentDimension(?Dimension $parent, Dimension $dimension): void
    {
        if ($parent !== null && $parent !== $dimension) {
            throw InvalidGeometry::mixedDimensions($parent, $dimension);
        }
    }

    /** @param list<mixed> $coordinates */
    private static function getFirstPositionLength(GeometryKind $kind, array $coordinates): ?int
    {
        $depth = match ($kind) {
            GeometryKind::POINT => 0,
            GeometryKind::LINESTRING, GeometryKind::CIRCULARSTRING, GeometryKind::MULTIPOINT => 1,
            GeometryKind::POLYGON, GeometryKind::TRIANGLE, GeometryKind::MULTILINESTRING => 2,
            default => 3,
        };

        return self::findPositionLength($coordinates, $depth);
    }

    /** @param array<mixed> $coordinates */
    private static function findPositionLength(array $coordinates, int $depth): ?int
    {
        if ($depth === 0) {
            return $coordinates === [] ? null : count($coordinates);
        }

        foreach ($coordinates as $child) {
            /** @var array<mixed> $child */
            $length = self::findPositionLength($child, $depth - 1);

            if ($length !== null) {
                return $length;
            }
        }

        return null;
    }

    /**
     * @return array{self::WORD|self::NUMBER|self::SYMBOL, string, int}
     *
     * @throws InvalidGeometry
     */
    private function next(): array
    {
        if (! isset($this->tokens[$this->current])) {
            throw InvalidGeometry::malformedWkt('unexpected end of input', strlen($this->wkt));
        }

        return $this->tokens[$this->current++];
    }

    private function acceptWord(string $word): bool
    {
        if (
            isset($this->tokens[$this->current])
            && $this->tokens[$this->current][0] === self::WORD
            && $this->tokens[$this->current][1] === $word
        ) {
            $this->current++;

            return true;
        }

        return false;
    }

    private function isSymbol(string $symbol): bool
    {
        return isset($this->tokens[$this->current])
            && $this->tokens[$this->current][0] === self::SYMBOL
            && $this->tokens[$this->current][1] === $symbol;
    }

    private function acceptSymbol(string $symbol): bool
    {
        if (
            isset($this->tokens[$this->current])
            && $this->tokens[$this->current][0] === self::SYMBOL
            && $this->tokens[$this->current][1] === $symbol
        ) {
            $this->current++;

            return true;
        }

        return false;
    }

    /** @throws InvalidGeometry */
    private function expectSymbol(string $symbol): void
    {
        if (! $this->acceptSymbol($symbol)) {
            throw InvalidGeometry::malformedWkt(sprintf('expected "%s"', $symbol), $this->getOffset());
        }
    }

    private function getOffset(): int
    {
        return $this->tokens[$this->current][2] ?? strlen($this->wkt);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

/**
 * The coordinate dimension of a geometry: X and Y, optionally with an elevation (Z) and/or a measure (M).
 */
enum Dimension: string
{
    case XY   = 'XY';
    case XYZ  = 'XYZ';
    case XYM  = 'XYM';
    case XYZM = 'XYZM';

    public static function fromFlags(bool $hasZ, bool $hasM): self
    {
        if ($hasZ) {
            return $hasM ? self::XYZM : self::XYZ;
        }

        return $hasM ? self::XYM : self::XY;
    }

    public function hasZ(): bool
    {
        return $this === self::XYZ || $this === self::XYZM;
    }

    public function hasM(): bool
    {
        return $this === self::XYM || $this === self::XYZM;
    }

    /**
     * Returns the number of coordinates of a position.
     *
     * @return int<2, 4>
     */
    public function getCoordinateCount(): int
    {
        return match ($this) {
            self::XY => 2,
            self::XYZ, self::XYM => 3,
            self::XYZM => 4,
        };
    }
}

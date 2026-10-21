<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Index;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;

use function is_finite;

/**
 * The extent of the geometries indexed by a spatial index, in the coordinates of their spatial reference system.
 */
final readonly class BoundingBox
{
    /** @throws InvalidIndexDefinition */
    public function __construct(
        private float $minX,
        private float $minY,
        private float $maxX,
        private float $maxY,
    ) {
        if (
            ! is_finite($minX) || ! is_finite($minY) || ! is_finite($maxX) || ! is_finite($maxY)
            || $minX >= $maxX || $minY >= $maxY
        ) {
            throw InvalidIndexDefinition::fromInvalidBoundingBox($minX, $minY, $maxX, $maxY);
        }
    }

    public function getMinX(): float
    {
        return $this->minX;
    }

    public function getMinY(): float
    {
        return $this->minY;
    }

    public function getMaxX(): float
    {
        return $this->maxX;
    }

    public function getMaxY(): float
    {
        return $this->maxY;
    }
}

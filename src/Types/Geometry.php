<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use InvalidArgumentException;
use Stringable;

/**
 * Value object representing spatial geometry data using GeoJSON format.
 *
 * This class provides a clean abstraction for spatial data that works across different
 * database platforms. GeoJSON is the standardized format that includes SRID information
 * within the format itself via the CRS property.
 */
final class Geometry implements Stringable
{
    private function __construct(
        private readonly GeoJSON $geoJson,
        private readonly int|null $srid,
    ) {
    }

    /**
     * Creates a Geometry from GeoJSON string.
     *
     * GeoJSON format includes SRID information within the CRS property according to RFC 7946.
     * This provides a standardized, platform-agnostic representation.
     *
     * @param string $json GeoJSON string representation of the geometry
     *
     * @throws InvalidArgumentException If the GeoJSON format is invalid.
     */
    public static function fromGeoJSON(string $json): self
    {
        $geoJson = GeoJSON::fromString($json);
        $srid    = $geoJson->getSrid();

        return new self($geoJson, $srid);
    }

    /**
     * Returns the GeoJSON representation.
     */
    public function getGeoJSON(): GeoJSON
    {
        return $this->geoJson;
    }

    /**
     * Returns the Spatial Reference System Identifier, if set.
     *
     * This is extracted from the CRS property in the GeoJSON.
     */
    public function getSrid(): int|null
    {
        return $this->srid;
    }

    /**
     * Returns GeoJSON string representation.
     */
    public function toGeoJSON(): string
    {
        return $this->geoJson->toString();
    }

    /**
     * Returns string representation of the geometry in GeoJSON format.
     */
    public function __toString(): string
    {
        return $this->geoJson->toString();
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Stringable;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Value object representing GeoJSON geometry data with optional SRID metadata.
 *
 * This class validates and wraps GeoJSON geometry data according to RFC 7946 specification.
 * It provides a clean abstraction for transporting pure geometry data with SRID across
 * different database platforms. Feature and FeatureCollection types are intentionally
 * excluded as they are not suitable for database column storage.
 *
 * Supported geometry types:
 * - Point, LineString, Polygon
 * - MultiPoint, MultiLineString, MultiPolygon
 * - GeometryCollection
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7946
 */
final class GeoJSON implements Stringable
{
    /**
     * Valid GeoJSON geometry types according to RFC 7946.
     *
     * Note: Feature and FeatureCollection are not included as they are not supported
     * for database storage. This class focuses on pure geometry transport with SRID.
     */
    private const VALID_TYPES = [
        'Point' => true,
        'LineString' => true,
        'Polygon' => true,
        'MultiPoint' => true,
        'MultiLineString' => true,
        'MultiPolygon' => true,
        'GeometryCollection' => true,
    ];

    /** @param array<string, mixed> $data Parsed GeoJSON data as associative array */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * Creates a GeoJSON value object from a JSON string.
     *
     * Validates the JSON structure and ensures it conforms to RFC 7946 geometry types.
     *
     * @param string $json GeoJSON string conforming to RFC 7946
     *
     * @throws InvalidArgumentException If the JSON is invalid or doesn't conform to GeoJSON spec.
     */
    public static function fromString(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                sprintf('Invalid JSON string: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('GeoJSON must be a JSON object, not a scalar or array');
        }

        self::validate($data);

        return new self($data);
    }

    /**
     * Validates GeoJSON data structure according to RFC 7946.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException If validation fails.
     */
    private static function validate(array $data): void
    {
        if (! isset($data['type'])) {
            throw new InvalidArgumentException('GeoJSON object must have a "type" property');
        }

        if (! is_string($data['type'])) {
            throw new InvalidArgumentException('GeoJSON "type" must be a string');
        }

        if (! isset(self::VALID_TYPES[$data['type']])) {
            throw new InvalidArgumentException(
                sprintf('Invalid GeoJSON type "%s"', $data['type']),
            );
        }

        $type = $data['type'];

        // Validate geometry objects (only pure geometries, no Features/FeatureCollections)
        if ($type === 'GeometryCollection') {
            if (! isset($data['geometries'])) {
                throw new InvalidArgumentException('GeoJSON GeometryCollection must have a "geometries" property');
            }

            if (! is_array($data['geometries'])) {
                throw new InvalidArgumentException('GeoJSON "geometries" must be an array');
            }
        } else {
            // All other geometry types must have coordinates
            if (! isset($data['coordinates'])) {
                throw new InvalidArgumentException(
                    sprintf('GeoJSON %s must have a "coordinates" property', $type),
                );
            }

            if (! is_array($data['coordinates'])) {
                throw new InvalidArgumentException(
                    sprintf('GeoJSON %s "coordinates" must be an array', $type),
                );
            }
        }

        // Validate CRS if present (for SRID support)
        if (isset($data['crs'])) {
            self::validateCrs($data['crs']);
        }

        // Validate bbox if present
        if (isset($data['bbox']) && ! is_array($data['bbox'])) {
            throw new InvalidArgumentException('GeoJSON "bbox" must be an array');
        }
    }

    /**
     * Validates the CRS (Coordinate Reference System) property.
     *
     * @throws InvalidArgumentException If CRS is invalid.
     */
    private static function validateCrs(mixed $crs): void
    {
        if (! is_array($crs)) {
            throw new InvalidArgumentException('GeoJSON "crs" must be an object');
        }

        if (! isset($crs['type']) || $crs['type'] !== 'name') {
            throw new InvalidArgumentException('GeoJSON "crs" must have type "name"');
        }

        if (! isset($crs['properties']) || ! is_array($crs['properties'])) {
            throw new InvalidArgumentException('GeoJSON "crs" must have a "properties" object');
        }

        if (! isset($crs['properties']['name']) || ! is_string($crs['properties']['name'])) {
            throw new InvalidArgumentException('GeoJSON "crs.properties" must have a "name" string');
        }
    }

    /**
     * Returns the SRID (Spatial Reference System Identifier) if specified in CRS.
     *
     * Extracts SRID from CRS property in formats:
     * - "EPSG:4326"
     * - "urn:ogc:def:crs:EPSG::4326"
     * - "http://www.opengis.net/def/crs/EPSG/0/4326"
     */
    public function getSrid(): int|null
    {
        if (! isset($this->data['crs']['properties']['name'])) {
            return null;
        }

        $crsName = $this->data['crs']['properties']['name'];

        // Match EPSG:4326 format
        if (preg_match('/EPSG[:\\/](\d+)$/i', $crsName, $matches) === 1) {
            return (int) $matches[1];
        }

        // Match urn:ogc:def:crs:EPSG::4326 format
        if (preg_match('/urn:ogc:def:crs:EPSG::(\d+)$/i', $crsName, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Returns the GeoJSON data as a JSON string.
     */
    public function toString(): string
    {
        try {
            return json_encode($this->data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                sprintf('Failed to encode GeoJSON: %s', $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Returns the GeoJSON data as a JSON string.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}

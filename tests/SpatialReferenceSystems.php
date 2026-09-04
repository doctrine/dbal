<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

/**
 * Common SRID (Spatial Reference System Identifier) constants for spatial type tests.
 *
 * These EPSG codes define coordinate reference systems used across spatial functionality tests.
 */
final class SpatialReferenceSystems
{
    /**
     * EPSG:4326 - WGS 84 coordinate system.
     *
     * The most commonly used geographic coordinate system (latitude/longitude).
     * Used by GPS and most web mapping applications.
     */
    public const SRID_WGS84 = 4326;

    /**
     * EPSG:32633 - WGS 84 / UTM zone 33N.
     *
     * A projected coordinate system for central Europe.
     * Used to test different SRID values in the same table.
     */
    public const SRID_UTM_33N = 32633;

    /**
     * EPSG:4267 - NAD27 coordinate system.
     *
     * North American Datum 1927, a geodetic datum for North America.
     * Used to test different SRID values for GEOGRAPHY columns.
     */
    public const SRID_NAD27 = 4267;

    /**
     * EPSG:3857 - WGS 84 / Pseudo-Mercator (Web Mercator).
     *
     * Used by web mapping services like Google Maps, OpenStreetMap, and Bing Maps.
     * Projects the spherical earth onto a flat surface for web display.
     */
    public const SRID_WEB_MERCATOR = 3857;
}

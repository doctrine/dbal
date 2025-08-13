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
}

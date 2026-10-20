<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

/**
 * The named spatial relationships between two geometries of the OGC Simple Features specification.
 */
enum SpatialRelation: string
{
    case CONTAINS   = 'CONTAINS';
    case CROSSES    = 'CROSSES';
    case DISJOINT   = 'DISJOINT';
    case EQUALS     = 'EQUALS';
    case INTERSECTS = 'INTERSECTS';
    case OVERLAPS   = 'OVERLAPS';
    case TOUCHES    = 'TOUCHES';
    case WITHIN     = 'WITHIN';
}

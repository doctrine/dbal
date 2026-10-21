<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Spatial;

/**
 * The set-theoretic operations between two geometries of the OGC Simple Features specification, which return a
 * geometry.
 */
enum SpatialSetOperation: string
{
    case DIFFERENCE           = 'DIFFERENCE';
    case INTERSECTION         = 'INTERSECTION';
    case SYMMETRIC_DIFFERENCE = 'SYMMETRIC_DIFFERENCE';
    case UNION                = 'UNION';
}

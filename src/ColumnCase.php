<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Contains portable column case conversions.
 */
enum ColumnCase
{
    /**
     * Convert column names to upper case.
     */
    case UPPER;

    /**
     * Convert column names to lower case.
     */
    case LOWER;
}

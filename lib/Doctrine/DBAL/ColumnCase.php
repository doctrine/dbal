<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Contains portable column case conversions.
 */
final class ColumnCase
{
    /**
     * Convert column names to upper case.
     *
     * @see \PDO::CASE_UPPER
     */
    public const UPPER = 1;

    /**
     * Convert column names to lower case.
     *
     * @see \PDO::CASE_LOWER
     */
    public const LOWER = 2;

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}

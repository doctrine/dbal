<?php

namespace Doctrine\DBAL;

/**
 * Contains portable column case conversions.
 */
class ColumnCase
{
    /**
     * Convert column names to upper case.
     *
     * @see \PDO::CASE_UPPER
     */
    public const UPPER = \PDO::CASE_UPPER;

    /**
     * Convert column names to lower case.
     *
     * @see \PDO::CASE_LOWER
     */
    public const LOWER = \PDO::CASE_LOWER;

    /**
     * This class cannot be instantiated.
     */
    final private function __construct()
    {
    }
}

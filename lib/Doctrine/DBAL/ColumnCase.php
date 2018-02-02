<?php

namespace Doctrine\DBAL;

use Doctrine\Enumeration\Enumerated;

/**
 * Contains portable column case conversions.
 */
final class ColumnCase
{
    use Enumerated;

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

    public static function UPPER() : self
    {
        return self::get(self::UPPER);
    }

    public static function LOWER() : self
    {
        return self::get(self::LOWER);
    }
}

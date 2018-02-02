<?php

namespace Doctrine\DBAL;

use Doctrine\Enumeration\Enumerated;

/**
 * Contains statement fetch modes.
 */
final class FetchMode
{
    use Enumerated;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by column name as returned in the corresponding result set. If the result
     * set contains multiple columns with the same name, the statement returns
     * only a single value per column name.
     *
     * @see \PDO::FETCH_ASSOC
     */
    public const ASSOCIATIVE = 2;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by column number as returned in the corresponding result set, starting at
     * column 0.
     *
     * @see \PDO::FETCH_NUM
     */
    public const NUMERIC = 3;

    /**
     * Specifies that the fetch method shall return each row as an array indexed
     * by both column name and number as returned in the corresponding result set,
     * starting at column 0.
     *
     * @see \PDO::FETCH_BOTH
     */
    public const MIXED = 4;

    /**
     * Specifies that the fetch method shall return each row as an object with
     * property names that correspond to the column names returned in the result
     * set.
     *
     * @see \PDO::FETCH_OBJ
     */
    public const STANDARD_OBJECT = 5;

    /**
     * Specifies that the fetch method shall return only a single requested
     * column from the next row in the result set.
     *
     * @see \PDO::FETCH_COLUMN
     */
    public const COLUMN = 7;

    /**
     * Specifies that the fetch method shall return a new instance of the
     * requested class, mapping the columns to named properties in the class.
     *
     * @see \PDO::FETCH_CLASS
     */
    public const CUSTOM_OBJECT = 8;

    public static function ASSOCIATIVE() : self
    {
        return self::get(self::ASSOCIATIVE);
    }

    public static function NUMERIC() : self
    {
        return self::get(self::NUMERIC);
    }

    public static function MIXED() : self
    {
        return self::get(self::MIXED);
    }

    public static function STANDARD_OBJECT() : self
    {
        return self::get(self::STANDARD_OBJECT);
    }

    public static function COLUMN() : self
    {
        return self::get(self::COLUMN);
    }

    public static function CUSTOM_OBJECT() : self
    {
        return self::get(self::CUSTOM_OBJECT);
    }
}

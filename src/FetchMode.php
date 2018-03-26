<?php

namespace Doctrine\DBAL;

/**
 * Contains statement fetch modes.
 */
final class FetchMode
{
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
     * Specifies that the fetch method shall return only a single requested
     * column from the next row in the result set.
     *
     * @see \PDO::FETCH_COLUMN
     */
    public const COLUMN = 7;

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}

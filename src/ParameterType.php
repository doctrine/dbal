<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Contains statement parameter types.
 */
final class ParameterType
{
    /**
     * Represents the SQL NULL data type.
     *
     * @see \PDO::PARAM_NULL
     */
    public const NULL = 0;

    /**
     * Represents the SQL INTEGER data type.
     *
     * @see \PDO::PARAM_INT
     */
    public const INTEGER = 1;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     *
     * @see \PDO::PARAM_STR
     */
    public const STRING = 2;

    /**
     * Represents the SQL large object data type.
     *
     * @see \PDO::PARAM_LOB
     */
    public const LARGE_OBJECT = 3;

    /**
     * Represents a boolean data type.
     *
     * @see \PDO::PARAM_BOOL
     */
    public const BOOLEAN = 5;

    /**
     * Represents a binary string data type.
     */
    public const BINARY = 16;

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}

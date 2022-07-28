<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Statement parameter type.
 */
enum ParameterType
{
    /**
     * Represents the SQL NULL data type.
     */
    case NULL;

    /**
     * Represents the SQL INTEGER data type.
     */
    case INTEGER;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     */
    case STRING;

    /**
     * Represents the SQL large object data type.
     */
    case LARGE_OBJECT;

    /**
     * Represents a boolean data type.
     */
    case BOOLEAN;

    /**
     * Represents a binary string data type.
     */
    case BINARY;

    /**
     * Represents an ASCII string data type
     */
    case ASCII;
}

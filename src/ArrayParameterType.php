<?php

namespace Doctrine\DBAL;

final class ArrayParameterType
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    public const INTEGER = ParameterType::INTEGER + self::ARRAY_PARAM_OFFSET;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    public const STRING = ParameterType::STRING + self::ARRAY_PARAM_OFFSET;

    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    public const ASCII = ParameterType::ASCII + self::ARRAY_PARAM_OFFSET;

    /**
     * Offset by which PARAM_* constants are detected as arrays of the param type.
     */
    private const ARRAY_PARAM_OFFSET = 100;

    /**
     * @internal
     *
     * @psalm-param self::INTEGER|self::STRING|self::ASCII $type
     *
     * @psalm-return ParameterType::INTEGER|ParameterType::STRING|ParameterType::ASCII
     */
    public static function toElementParameterType(int $type): int
    {
        return $type - self::ARRAY_PARAM_OFFSET;
    }

    private function __construct()
    {
    }
}

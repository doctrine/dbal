<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\ArrayParameters\Exception\InvalidParameterType;

final class ArrayParameterType
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    public const INTEGER = 101;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    public const STRING = 102;

    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    public const ASCII = 117;

    /**
     * @internal
     *
     * @psalm-param self::INTEGER|self::STRING|self::ASCII $type
     */
    public static function toElementParameterType(int $type): ParameterType
    {
        return match ($type) {
            self::INTEGER => ParameterType::INTEGER,
            self::STRING => ParameterType::STRING,
            self::ASCII => ParameterType::ASCII,
            default => throw InvalidParameterType::new($type),
        };
    }

    private function __construct()
    {
    }
}

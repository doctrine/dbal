<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

enum ArrayParameterType
{
    /**
     * Represents an array of ints to be expanded by Doctrine SQL parsing.
     */
    case INTEGER;

    /**
     * Represents an array of strings to be expanded by Doctrine SQL parsing.
     */
    case STRING;

    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    case ASCII;

    /**
     * Represents an array of ascii strings to be expanded by Doctrine SQL parsing.
     */
    case BINARY;

    /** @internal */
    public static function toElementParameterType(self $type): ParameterType
    {
        return match ($type) {
            self::INTEGER => ParameterType::INTEGER,
            self::STRING => ParameterType::STRING,
            self::ASCII => ParameterType::ASCII,
            self::BINARY => ParameterType::BINARY,
        };
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use function strtolower;
use function strtoupper;

/**
 * Defines how a database platform folds the case of unquoted identifiers.
 */
enum UnquotedIdentifierFolding
{
    /**
     * Represents upper-case folding of unquoted identifiers.
     */
    case UPPER;

    /**
     * Represents lower-case folding of unquoted identifiers.
     */
    case LOWER;

    /**
     * Represents no folding of unquoted identifiers.
     */
    case NONE;

    /**
     * Applies case folding to an unquoted identifier as a database platform would when processing an SQL statement.
     *
     * @param non-empty-string $value
     *
     * @return non-empty-string
     */
    public function foldUnquotedIdentifier(string $value): string
    {
        return match ($this) {
            self::UPPER => strtoupper($value),
            self::LOWER => strtolower($value),
            self::NONE => $value,
        };
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\InsertModify;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;
use function get_debug_type;
use function implode;
use function sprintf;

/**
 * Thrown when a platform does not support the requested INSERT modifier.
 *
 * Requesting an unsupported modifier raises this rather than emitting SQL the
 * platform would reject at runtime with a driver-level syntax error.
 */
final class UnsupportedInsertModifier extends ConnectionException
{
    /** @param list<InsertModify> $supported */
    public static function new(
        AbstractPlatform $platform,
        InsertModify $modify,
        array $supported = [],
    ): self {
        $supportedNames = array_map(
            static fn (InsertModify $case): string => $case->value,
            $supported,
        );

        $message = sprintf(
            '%s does not support the INSERT modifier "%s".',
            get_debug_type($platform),
            $modify->value,
        );

        if ($supportedNames !== []) {
            $message .= ' Supported modifiers: ' . implode(', ', $supportedNames) . '.';
        }

        return new self($message);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use function implode;
use function sprintf;

final class UnknownDriver extends InvalidArgumentException
{
    /** @param string[] $knownDrivers */
    public static function new(string $unknownDriverName, array $knownDrivers): self
    {
        return new self(
            sprintf(
                'The given driver "%s" is unknown, Doctrine currently supports only the following drivers: %s',
                $unknownDriverName,
                implode(', ', $knownDrivers),
            ),
        );
    }
}

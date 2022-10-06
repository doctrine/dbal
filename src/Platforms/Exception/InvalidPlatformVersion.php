<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Exception;

use function sprintf;

/** @psalm-immutable */
final class InvalidPlatformVersion extends Exception implements PlatformException
{
    /**
     * Returns a new instance for an invalid specified platform version.
     *
     * @param string $version        The invalid platform version given.
     * @param string $expectedFormat The expected platform version format.
     */
    public static function new(string $version, string $expectedFormat): self
    {
        return new self(sprintf(
            'Invalid platform version "%s" specified. The platform version has to be specified in the format: "%s".',
            $version,
            $expectedFormat,
        ));
    }
}

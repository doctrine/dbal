<?php

namespace Doctrine\DBAL;

use function str_replace;
use function strtoupper;
use function version_compare;

/**
 * Class to store and retrieve the version of Doctrine.
 *
 * @internal
 * @deprecated Refrain from checking the DBAL version at runtime.
 */
class Version
{
    /**
     * Current Doctrine Version.
     */
    public const VERSION = '2.13.9';

    /**
     * Compares a Doctrine version with the current one.
     *
     * @param string $version The Doctrine version to compare to.
     *
     * @return int -1 if older, 0 if it is the same, 1 if version passed as argument is newer.
     */
    public static function compare($version)
    {
        $version = str_replace(' ', '', strtoupper($version));

        return version_compare($version, self::VERSION);
    }
}

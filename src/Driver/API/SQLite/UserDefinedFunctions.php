<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\SQLite;

use function sqrt;
use function strpos;

/**
 * User-defined SQLite functions.
 *
 * @internal
 */
final class UserDefinedFunctions
{
    /** @param callable(string, callable, int): bool $callback */
    public static function register(callable $callback): void
    {
        $callback('sqrt', sqrt(...), 1);
        $callback('mod', self::mod(...), 2);
        $callback('locate', self::locate(...), -1);
    }

    /**
     * User-defined function that implements MOD().
     */
    private static function mod(int $a, int $b): int
    {
        return $a % $b;
    }

    /**
     * User-defined function that implements LOCATE().
     */
    private static function locate(string $str, string $substr, int $offset = 0): int
    {
        // SQL's LOCATE function works on 1-based positions, while PHP's strpos works on 0-based positions.
        // So we have to make them compatible if an offset is given.
        if ($offset > 0) {
            $offset -= 1;
        }

        $pos = strpos($str, $substr, $offset);

        if ($pos !== false) {
            return $pos + 1;
        }

        return 0;
    }

    private function __construct()
    {
    }
}

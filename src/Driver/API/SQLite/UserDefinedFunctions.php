<?php

namespace Doctrine\DBAL\Driver\API\SQLite;

use function strpos;

/**
 * User-defined SQLite functions.
 *
 * @internal
 */
final class UserDefinedFunctions
{
    /**
     * User-defined function that implements MOD().
     *
     * @param int $a
     * @param int $b
     */
    public static function mod($a, $b): int
    {
        return $a % $b;
    }

    /**
     * User-defined function that implements LOCATE().
     *
     * @param string $str
     * @param string $substr
     * @param int    $offset
     */
    public static function locate($str, $substr, $offset = 0): int
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
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\SQLite;

use function array_merge;
use function strpos;

/**
 * User-defined SQLite functions.
 *
 * @internal
 */
final class UserDefinedFunctions
{
    private const DEFAULT_FUNCTIONS = [
        'sqrt' => ['callback' => 'sqrt', 'numArgs' => 1],
        'mod'  => ['callback' => [self::class, 'mod'], 'numArgs' => 2],
        'locate'  => ['callback' => [self::class, 'locate'], 'numArgs' => -1],
    ];

    /**
     * @param callable(string, callable, int): bool                  $callback
     * @param array<string, array{callback: callable, numArgs: int}> $additionalFunctions
     */
    public static function register(callable $callback, array $additionalFunctions = []): void
    {
        $userDefinedFunctions = array_merge(self::DEFAULT_FUNCTIONS, $additionalFunctions);

        foreach ($userDefinedFunctions as $function => $data) {
            $callback($function, $data['callback'], $data['numArgs']);
        }
    }

    /**
     * User-defined function that implements MOD().
     */
    public static function mod(int $a, int $b): int
    {
        return $a % $b;
    }

    /**
     * User-defined function that implements LOCATE().
     */
    public static function locate(string $str, string $substr, int $offset = 0): int
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

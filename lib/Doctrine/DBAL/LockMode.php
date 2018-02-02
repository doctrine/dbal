<?php

namespace Doctrine\DBAL;

use Doctrine\Enumeration\Enumerated;

/**
 * Contains all DBAL LockModes.
 */
final class LockMode
{
    use Enumerated;

    public const NONE = 0;
    public const OPTIMISTIC = 1;
    public const PESSIMISTIC_READ = 2;
    public const PESSIMISTIC_WRITE = 4;

    public static function NONE() : self
    {
        return self::get(self::NONE);
    }

    public static function OPTIMISTIC() : self
    {
        return self::get(self::OPTIMISTIC);
    }

    public static function PESSIMISTIC_READ() : self
    {
        return self::get(self::PESSIMISTIC_READ);
    }

    public static function PESSIMISTIC_WRITE() : self
    {
        return self::get(self::PESSIMISTIC_WRITE);
    }
}

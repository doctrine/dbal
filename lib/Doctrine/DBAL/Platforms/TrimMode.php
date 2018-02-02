<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\Enumeration\Enumerated;

final class TrimMode
{
    use Enumerated;

    public const UNSPECIFIED = 0;

    public const LEADING = 1;

    public const TRAILING = 2;

    public const BOTH = 3;

    public static function UNSPECIFIED() : self
    {
        return self::get(self::UNSPECIFIED);
    }

    public static function LEADING() : self
    {
        return self::get(self::LEADING);
    }

    public static function TRAILING() : self
    {
        return self::get(self::TRAILING);
    }

    public static function BOTH() : self
    {
        return self::get(self::BOTH);
    }
}

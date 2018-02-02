<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\Enumeration\Enumerated;

final class DateIntervalUnit
{
    use Enumerated;

    public const SECOND = 'SECOND';

    public const MINUTE = 'MINUTE';

    public const HOUR = 'HOUR';

    public const DAY = 'DAY';

    public const WEEK = 'WEEK';

    public const MONTH = 'MONTH';

    public const QUARTER = 'QUARTER';

    public const YEAR = 'YEAR';

    public static function SECOND() : self
    {
        return self::get(self::SECOND);
    }

    public static function MINUTE() : self
    {
        return self::get(self::MINUTE);
    }

    public static function HOUR() : self
    {
        return self::get(self::HOUR);
    }

    public static function DAY() : self
    {
        return self::get(self::DAY);
    }

    public static function WEEK() : self
    {
        return self::get(self::WEEK);
    }

    public static function MONTH() : self
    {
        return self::get(self::MONTH);
    }

    public static function QUARTER() : self
    {
        return self::get(self::QUARTER);
    }

    public static function YEAR() : self
    {
        return self::get(self::YEAR);
    }
}

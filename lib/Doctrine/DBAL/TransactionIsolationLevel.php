<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\Enumeration\Enumerated;

final class TransactionIsolationLevel
{
    use Enumerated;

    /**
     * Transaction isolation level READ UNCOMMITTED.
     */
    public const READ_UNCOMMITTED = 1;

    /**
     * Transaction isolation level READ COMMITTED.
     */
    public const READ_COMMITTED = 2;

    /**
     * Transaction isolation level REPEATABLE READ.
     */
    public const REPEATABLE_READ = 3;

    /**
     * Transaction isolation level SERIALIZABLE.
     */
    public const SERIALIZABLE = 4;

    public static function READ_UNCOMMITTED() : self
    {
        return self::get(self::READ_UNCOMMITTED);
    }

    public static function READ_COMMITTED() : self
    {
        return self::get(self::READ_COMMITTED);
    }

    public static function REPEATABLE_READ() : self
    {
        return self::get(self::REPEATABLE_READ);
    }

    public static function SERIALIZABLE() : self
    {
        return self::get(self::SERIALIZABLE);
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Legacy Class that keeps BC for using the legacy APIs fetch()/fetchAll().
 */
class FetchMode
{
    /** @link PDO::FETCH_ASSOC */
    final public const ASSOCIATIVE = 2;

    /** @link PDO::FETCH_NUM */
    final public const NUMERIC = 3;

    /** @link PDO::FETCH_COLUMN */
    final public const COLUMN = 7;
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Contains all DBAL LockModes.
 */
class LockMode
{
    final public const NONE              = 0;
    final public const OPTIMISTIC        = 1;
    final public const PESSIMISTIC_READ  = 2;
    final public const PESSIMISTIC_WRITE = 4;

    /**
     * Private constructor. This class cannot be instantiated.
     *
     * @codeCoverageIgnore
     */
    final private function __construct()
    {
    }
}

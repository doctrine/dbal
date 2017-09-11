<?php

namespace Doctrine\DBAL;

/**
 * Contains all DBAL LockModes.
 *
 * @link   www.doctrine-project.org
 * @since  1.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Roman Borschel <roman@code-factory.org>
 */
class LockMode
{
    const NONE = 0;
    const OPTIMISTIC = 1;
    const PESSIMISTIC_READ = 2;
    const PESSIMISTIC_WRITE = 4;

    /**
     * Private constructor. This class cannot be instantiated.
     */
    final private function __construct()
    {
    }
}

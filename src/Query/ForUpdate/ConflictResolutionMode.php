<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\ForUpdate;

enum ConflictResolutionMode
{
    /**
     * Wait for the row to be unlocked
     */
    case ORDINARY;

    /**
     * Skip the row if it is locked
     */
    case SKIP_LOCKED;
}

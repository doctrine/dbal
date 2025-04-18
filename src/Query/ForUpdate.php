<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;

/** @internal */
final readonly class ForUpdate
{
    public function __construct(
        private ConflictResolutionMode $conflictResolutionMode,
    ) {
    }

    public function getConflictResolutionMode(): ConflictResolutionMode
    {
        return $this->conflictResolutionMode;
    }
}

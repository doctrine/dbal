<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final class ForUpdate
{
    public function __construct(
        private readonly int $conflictResolutionMode,
    ) {
    }

    public function getConflictResolutionMode(): int
    {
        return $this->conflictResolutionMode;
    }
}

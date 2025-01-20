<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * Represents the information about whether the constraint is or can be deferred.
 */
enum Deferrability: string
{
    case NOT_DEFERRABLE = 'NOT DEFERRABLE';
    case DEFERRABLE     = 'DEFERRABLE';
    case DEFERRED       = 'INITIALLY DEFERRED';

    /**
     * Returns the SQL representation of the referential action.
     */
    public function toSQL(): string
    {
        return $this->value;
    }
}

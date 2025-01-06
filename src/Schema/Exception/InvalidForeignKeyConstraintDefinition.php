<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidForeignKeyConstraintDefinition extends LogicException implements SchemaException
{
    public static function referencedTableNameNotSet(): self
    {
        return new self('Foreign key constraint referenced table name is not set.');
    }

    public static function referencingColumnNamesNotSet(): self
    {
        return new self('Foreign key constraint referencing column names are not set.');
    }

    public static function referencedColumnNamesNotSet(): self
    {
        return new self('Foreign key constraint referenced column names are not set.');
    }

    public static function nonMatchingColumnNameCounts(int $referencingCount, int $referencedCount): self
    {
        return new self(sprintf(
            'The number of referencing column names (%d) does not match the number of referenced'
                . ' column names (%d).',
            $referencingCount,
            $referencedCount,
        ));
    }

    public static function nonDeferrableInitiallyDeferred(): self
    {
        return new self('A constraint cannot be non-deferrable and initially deferred at the same time.');
    }
}

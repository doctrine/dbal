<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidState extends LogicException implements SchemaException
{
    public static function foreignKeyConstraintHasInvalidReferencedTableName(string $constraintName): self
    {
        return new self(sprintf(
            'Foreign key constraint "%s" has invalid referenced table names.',
            $constraintName,
        ));
    }

    public static function foreignKeyConstraintHasInvalidReferencingColumnNames(string $constraintName): self
    {
        return new self(sprintf(
            'Foreign key constraint "%s" has one or more invalid referencing column names.',
            $constraintName,
        ));
    }

    public static function foreignKeyConstraintHasInvalidReferencedColumnNames(string $constraintName): self
    {
        return new self(sprintf(
            'Foreign key constraint "%s" has one or more invalid referenced column name.',
            $constraintName,
        ));
    }

    public static function foreignKeyConstraintHasInvalidMatchType(string $constraintName): self
    {
        return new self(sprintf('Foreign key constraint "%s" has invalid match type.', $constraintName));
    }

    public static function foreignKeyConstraintHasInvalidOnUpdateAction(string $constraintName): self
    {
        return new self(sprintf('Foreign key constraint "%s" has invalid ON UPDATE action.', $constraintName));
    }

    public static function foreignKeyConstraintHasInvalidOnDeleteAction(string $constraintName): self
    {
        return new self(sprintf('Foreign key constraint "%s" has invalid ON DELETE action.', $constraintName));
    }

    public static function foreignKeyConstraintHasInvalidDeferrability(string $constraintName): self
    {
        return new self(sprintf('Foreign key constraint "%s" has invalid deferrability.', $constraintName));
    }
}

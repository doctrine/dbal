<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

/** @psalm-immutable */
final class InvalidState extends LogicException implements SchemaException
{
    public static function uniqueConstraintHasInvalidColumnNames(string $constraintName): self
    {
        return new self(sprintf('Unique constraint "%s" has one or more invalid column name.', $constraintName));
    }

    public static function uniqueConstraintHasEmptyColumnNames(string $constraintName): self
    {
        return new self(sprintf('Unique constraint "%s" has no column names.', $constraintName));
    }
}

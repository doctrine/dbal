<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidUniqueConstraintDefinition extends LogicException implements SchemaException
{
    public static function fromDerivedConstraintWithDerivedIndex(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Unique constraint %s is derived and cannot hold a derived index.',
            $constraintName?->toString() ?? '<unnamed>',
        ));
    }

    public static function fromHeldIndexNotDerived(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Unique constraint %s holds an index that is not marked as derived.',
            $constraintName?->toString() ?? '<unnamed>',
        ));
    }

    public static function columnNamesAreNotSet(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Column names are not set for unique constraint %s.',
            $constraintName === null ? '<unnamed>' : $constraintName->toString(),
        ));
    }
}

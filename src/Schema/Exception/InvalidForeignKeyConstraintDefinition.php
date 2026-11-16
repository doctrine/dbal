<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidForeignKeyConstraintDefinition extends LogicException implements SchemaException
{
    public static function referencedTableNameNotSet(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Referenced table name is not set for foreign key constraint %s.',
            self::formatName($constraintName),
        ));
    }

    public static function referencingColumnNamesNotSet(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Referencing column names are not set for foreign key constraint %s.',
            self::formatName($constraintName),
        ));
    }

    public static function referencedColumnNamesNotSet(?UnqualifiedName $constraintName): self
    {
        return new self(sprintf(
            'Referenced column names are not set for foreign key constraint %s.',
            self::formatName($constraintName),
        ));
    }

    private static function formatName(?UnqualifiedName $constraintName): string
    {
        return $constraintName === null ? self::UNNAMED : $constraintName->toString();
    }
}

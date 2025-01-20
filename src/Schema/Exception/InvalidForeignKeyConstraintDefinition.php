<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

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
}

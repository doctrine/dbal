<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

final class UnspecifiedConstraintName extends InvalidArgumentException implements SchemaException
{
    public static function forPrimaryKeyConstraint(): self
    {
        return new self('Primary key constraint name is not specified.');
    }

    public static function forForeignKeyConstraint(): self
    {
        return new self('Foreign key constraint name is not specified.');
    }
}

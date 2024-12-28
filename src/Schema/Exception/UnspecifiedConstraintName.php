<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

/** @psalm-immutable */
final class UnspecifiedConstraintName extends InvalidArgumentException implements SchemaException
{
    public static function new(): self
    {
        return new self('Constraint name is not specified.');
    }
}

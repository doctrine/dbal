<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class PrimaryKeyAlreadyExists extends LogicException implements SchemaException
{
    public static function new(string $tableName): self
    {
        return new self(
            sprintf('Primary key was already defined on table "%s".', $tableName),
        );
    }
}

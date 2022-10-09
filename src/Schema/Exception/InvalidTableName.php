<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

use function sprintf;

/** @psalm-immutable */
final class InvalidTableName extends InvalidArgumentException implements SchemaException
{
    public static function new(string $tableName): self
    {
        return new self(sprintf('Invalid table name specified "%s".', $tableName));
    }
}

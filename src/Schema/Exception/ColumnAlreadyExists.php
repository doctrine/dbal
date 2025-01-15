<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class ColumnAlreadyExists extends LogicException implements SchemaException
{
    public static function new(string $tableName, string $columnName): self
    {
        return new self(sprintf('The column "%s" on table "%s" already exists.', $columnName, $tableName));
    }
}

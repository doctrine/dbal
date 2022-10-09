<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

/** @psalm-immutable */
final class TableAlreadyExists extends LogicException implements SchemaException
{
    public static function new(string $tableName): self
    {
        return new self(sprintf('The table with name "%s" already exists.', $tableName));
    }
}

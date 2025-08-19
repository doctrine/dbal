<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class ColumnAlreadyExists extends LogicException implements SchemaException
{
    public static function new(OptionallyQualifiedName $tableName, UnqualifiedName $columnName): self
    {
        return new self(
            sprintf(
                'The column %s on table %s already exists.',
                $columnName->toString(),
                $tableName->toString(),
            ),
        );
    }
}

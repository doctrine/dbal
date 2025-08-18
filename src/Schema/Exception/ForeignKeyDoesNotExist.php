<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class ForeignKeyDoesNotExist extends LogicException implements SchemaException
{
    public static function new(OptionallyQualifiedName $tableName, UnqualifiedName $foreignKeyName): self
    {
        return new self(
            sprintf(
                'There exists no foreign key with the name %s on table %s.',
                $foreignKeyName->toString(),
                $tableName->toString(),
            ),
        );
    }
}

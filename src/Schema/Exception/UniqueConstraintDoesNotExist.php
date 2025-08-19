<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class UniqueConstraintDoesNotExist extends LogicException implements SchemaException
{
    public static function new(OptionallyQualifiedName $tableName, UnqualifiedName $constraintName): self
    {
        return new self(
            sprintf(
                'There exists no unique constraint with the name "%s" on table %s.',
                $constraintName->toString(),
                $tableName->toString(),
            ),
        );
    }
}

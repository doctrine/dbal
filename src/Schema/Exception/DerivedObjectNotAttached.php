<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class DerivedObjectNotAttached extends LogicException implements SchemaException
{
    public static function fromIndex(OptionallyQualifiedName $tableName, UnqualifiedName $indexName): self
    {
        return new self(sprintf(
            'Derived index %s of table %s is not attached to any constraint.',
            $indexName->toString(),
            $tableName->toString(),
        ));
    }

    public static function fromUniqueConstraint(
        OptionallyQualifiedName $tableName,
        ?UnqualifiedName $constraintName,
    ): self {
        return new self(sprintf(
            'Derived unique constraint %s of table %s is not attached to any index.',
            $constraintName?->toString() ?? '<unnamed>',
            $tableName->toString(),
        ));
    }
}

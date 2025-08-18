<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class IndexAlreadyExists extends LogicException implements SchemaException
{
    public static function new(OptionallyQualifiedName $tableName, UnqualifiedName $indexName): self
    {
        return new self(
            sprintf(
                'An index with name %s was already defined on table %s.',
                $indexName->toString(),
                $tableName->toString(),
            ),
        );
    }
}

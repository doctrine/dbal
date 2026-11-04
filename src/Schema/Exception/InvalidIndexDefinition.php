<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Index\IndexMethod;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidIndexDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('Index name is not set.');
    }

    public static function columnsNotSet(UnqualifiedName $indexName): self
    {
        return new self(sprintf('Columns are not set for index %s.', $indexName->toString()));
    }

    public static function methodIncompatibleWithType(
        UnqualifiedName $indexName,
        IndexMethod $method,
        IndexType $type,
    ): self {
        return new self(sprintf(
            'Index %s cannot use method %s with type %s.',
            $indexName->toString(),
            $method->name,
            $type->name,
        ));
    }

    public static function methodIncompatibleWithClustering(UnqualifiedName $indexName, IndexMethod $method): self
    {
        return new self(sprintf(
            'Index %s cannot use method %s and be clustered.',
            $indexName->toString(),
            $method->name,
        ));
    }

    public static function fromNonPositiveColumnLength(UnqualifiedName $columnName, int $length): self
    {
        return new self(sprintf(
            'Indexed column length must be a positive integer, %d given for column %s.',
            $length,
            $columnName->toString(),
        ));
    }
}

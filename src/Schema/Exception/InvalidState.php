<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidState extends LogicException implements SchemaException
{
    public static function indexHasInvalidType(string $indexName): self
    {
        return new self(sprintf('Index "%s" has invalid type.', $indexName));
    }

    public static function indexHasInvalidPredicate(string $indexName): self
    {
        return new self(sprintf('Index "%s" has invalid predicate.', $indexName));
    }
}

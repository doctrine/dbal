<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

use function sprintf;

/** @psalm-immutable */
final class IndexNameInvalid extends InvalidArgumentException implements SchemaException
{
    public static function new(string $indexName): self
    {
        return new self(sprintf('Invalid index name "%s" given, has to be [a-zA-Z0-9_].', $indexName));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidState extends LogicException implements SchemaException
{
    public static function indexHasInvalidColumns(string $indexName): self
    {
        return new self(sprintf('Index "%s" has invalid columns.', $indexName));
    }
}

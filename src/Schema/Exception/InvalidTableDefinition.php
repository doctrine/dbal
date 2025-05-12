<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidTableDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('Table name is not set.');
    }

    public static function columnsNotSet(OptionallyQualifiedName $tableName): self
    {
        return new self(sprintf('Columns are not set for table %s.', $tableName->toString()));
    }
}

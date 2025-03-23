<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidState extends LogicException implements SchemaException
{
    public static function tableHasInvalidPrimaryKeyConstraint(string $tableName): self
    {
        return new self(sprintf('Table "%s" has invalid primary key constraint.', $tableName));
    }
}

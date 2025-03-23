<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

final class InvalidPrimaryKeyConstraintDefinition extends LogicException implements SchemaException
{
    public static function columnNamesNotSet(): self
    {
        return new self('Primary key constraint column names are not set.');
    }
}

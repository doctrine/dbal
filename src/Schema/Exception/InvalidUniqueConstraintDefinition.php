<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

final class InvalidUniqueConstraintDefinition extends LogicException implements SchemaException
{
    public static function columnNamesAreNotSet(): self
    {
        return new self('Unique constraint column names are not set.');
    }
}

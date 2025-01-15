<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

final class InvalidTableDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('Table name is not set.');
    }
}

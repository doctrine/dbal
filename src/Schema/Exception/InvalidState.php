<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

final class InvalidState extends LogicException implements SchemaException
{
    public static function tableDiffContainsUnnamedDroppedForeignKeyConstraints(): self
    {
        return new self('Table diff contains unnamed dropped foreign key constraints');
    }
}

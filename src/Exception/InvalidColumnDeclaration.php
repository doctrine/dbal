<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use LogicException;

use function sprintf;

final class InvalidColumnDeclaration extends LogicException implements Exception
{
    public static function fromInvalidColumnType(UnqualifiedName $columnName, InvalidColumnType $e): self
    {
        return new self(sprintf('Column "%s" has invalid type', $columnName->toString()), 0, $e);
    }
}

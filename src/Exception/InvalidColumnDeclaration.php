<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

use function sprintf;

final class InvalidColumnDeclaration extends LogicException implements Exception
{
    public static function fromInvalidColumnType(string $columnName, InvalidColumnType $e): self
    {
        return new self(sprintf('Column "%s" has invalid type', $columnName), 0, $e);
    }
}

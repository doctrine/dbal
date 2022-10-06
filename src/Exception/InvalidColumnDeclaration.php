<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

use function sprintf;

/** @psalm-immutable */
final class InvalidColumnDeclaration extends \Exception implements Exception
{
    public static function fromInvalidColumnType(string $columnName, InvalidColumnType $e): self
    {
        return new self(sprintf('Column "%s" has invalid type', $columnName), 0, $e);
    }
}

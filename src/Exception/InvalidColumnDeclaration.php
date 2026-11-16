<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

use function sprintf;

final class InvalidColumnDeclaration extends LogicException implements Exception
{
    public static function fromInvalidColumnType(?string $columnName, InvalidColumnType $e): self
    {
        if ($columnName === null || $columnName === '') {
            return new self('Column "<unnamed>" has invalid type', 0, $e);
        }

        return new self(sprintf('Column "%s" has invalid type', $columnName), 0, $e);
    }

    public static function fromMissingColumnType(string $columnName): self
    {
        return new self(sprintf(
            'Column "%s" has no type. The column definition must contain a "typeName" key.',
            $columnName,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use ArgumentCountError;
use Exception;

use function sprintf;

final class TypeArgumentCountError extends Exception implements TypesException
{
    public static function new(string $name, ArgumentCountError $previous): self
    {
        return new self(
            sprintf(
                'To register "%s" pass an instance to `Type::addType` instead.',
                $name,
            ),
            previous: $previous,
        );
    }
}

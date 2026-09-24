<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types\Exception;

use Exception;

use function sprintf;

final class TypeArgumentCountError extends Exception implements TypesException
{
    public static function fromClass(string $name, string $class): self
    {
        return new self(sprintf(
            'Cannot register type "%s" by class "%s": its constructor has required parameters. Pass an instance.',
            $name,
            $class,
        ));
    }
}

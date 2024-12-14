<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

/** @psalm-immutable */
final class InvalidIdentifier extends InvalidArgumentException implements SchemaException
{
    public static function fromEmpty(): self
    {
        return new self('Identifier cannot be empty.');
    }
}

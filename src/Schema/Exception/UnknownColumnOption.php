<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

use function sprintf;

/** @psalm-immutable */
final class UnknownColumnOption extends InvalidArgumentException implements SchemaException
{
    public static function new(string $name): self
    {
        return new self(
            sprintf('The "%s" column option is not supported.', $name),
        );
    }
}

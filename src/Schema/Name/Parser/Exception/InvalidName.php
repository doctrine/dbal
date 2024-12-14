<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser\Exception;

use Doctrine\DBAL\Schema\Name\Parser\Exception;
use InvalidArgumentException;

use function sprintf;

/** @internal */
class InvalidName extends InvalidArgumentException implements Exception
{
    public static function forUnqualifiedName(int $count): self
    {
        return new self(sprintf('An unqualified name must consist of one identifier, %d given.', $count));
    }

    public static function forOptionallyQualifiedName(int $count): self
    {
        return new self(
            sprintf('An optionally qualified name must consist of one or two identifiers, %d given.', $count),
        );
    }
}

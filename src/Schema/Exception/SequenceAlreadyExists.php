<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

/** @psalm-immutable */
final class SequenceAlreadyExists extends LogicException implements SchemaException
{
    public static function new(string $sequenceName): self
    {
        return new self(sprintf('The sequence "%s" already exists.', $sequenceName));
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;
use LogicException;

use function sprintf;

/** @psalm-immutable */
final class InvalidColumnIndex extends LogicException implements Exception
{
    public static function new(int $index): self
    {
        return new self(sprintf('Invalid column index "%s".', $index));
    }
}

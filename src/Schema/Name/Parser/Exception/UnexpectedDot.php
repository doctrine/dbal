<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name\Parser\Exception;

use Doctrine\DBAL\Schema\Name\Parser\Exception;
use LogicException;

use function sprintf;

/** @internal */
class UnexpectedDot extends LogicException implements Exception
{
    public static function new(int $offset): self
    {
        return new self(sprintf('Unexpected dot at offset %d.', $offset));
    }
}

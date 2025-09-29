<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Collections\Exception;

use Doctrine\DBAL\Schema\Collections\Exception;
use Doctrine\DBAL\Schema\Name;
use LogicException;

use function sprintf;

/** @internal */
final class SetAlreadyContainsName extends LogicException implements Exception
{
    public static function new(Name $name): self
    {
        return new self(sprintf('Set already contains name %s.', $name->toString()));
    }
}

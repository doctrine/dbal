<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;

use function sprintf;

/** @psalm-immutable */
final class ContradictingColumnOption extends SchemaException
{
    public static function new(string $name, string $duplicate): self
    {
        return new self(
            sprintf('The "%s" and "%s" column options are contradicting.', $name, $duplicate),
        );
    }
}

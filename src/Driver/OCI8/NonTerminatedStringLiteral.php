<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use function sprintf;

final class NonTerminatedStringLiteral extends OCI8Exception
{
    public static function new(int $offset) : self
    {
        return new self(
            sprintf(
                'The statement contains non-terminated string literal starting at offset %d.',
                $offset
            )
        );
    }
}

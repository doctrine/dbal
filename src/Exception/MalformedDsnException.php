<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

/** @psalm-immutable */
class MalformedDsnException extends InvalidArgumentException
{
    public static function new(): self
    {
        return new self('Malformed database connection URL');
    }
}

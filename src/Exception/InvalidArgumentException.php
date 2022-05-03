<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

/**
 * Exception to be thrown when invalid arguments are passed to any DBAL API
 *
 * @psalm-immutable
 */
class InvalidArgumentException extends Exception
{
    public static function fromEmptyCriteria(): self
    {
        return new self('Empty criteria was used, expected non-empty criteria');
    }
}

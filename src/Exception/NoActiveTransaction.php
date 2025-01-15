<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\ConnectionException;

final class NoActiveTransaction extends ConnectionException
{
    public static function new(): self
    {
        return new self('There is no active transaction.');
    }
}

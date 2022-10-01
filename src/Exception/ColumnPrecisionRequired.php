<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

/** @psalm-immutable */
final class ColumnPrecisionRequired extends Exception
{
    public static function new(): self
    {
        return new self('Column precision is not specified');
    }
}

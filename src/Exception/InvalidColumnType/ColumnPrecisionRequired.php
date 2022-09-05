<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception\InvalidColumnType;

use Doctrine\DBAL\Exception\InvalidColumnType;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class ColumnPrecisionRequired extends InvalidColumnType
{
    public static function new(): self
    {
        return new self('Column precision is not specified');
    }
}

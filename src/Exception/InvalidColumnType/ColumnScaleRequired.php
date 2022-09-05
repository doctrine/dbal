<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception\InvalidColumnType;

use Doctrine\DBAL\Exception\InvalidColumnType;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class ColumnScaleRequired extends InvalidColumnType
{
    public static function new(): self
    {
        return new self('Column scale is not specified');
    }
}

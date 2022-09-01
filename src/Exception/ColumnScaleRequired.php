<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\Exception;

/**
 * @psalm-immutable
 */
final class ColumnScaleRequired extends Exception
{
    public static function new(): self
    {
        return new self('Column scale is not specified');
    }
}

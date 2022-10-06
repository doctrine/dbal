<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Exception;

use function sprintf;

/** @psalm-immutable */
final class NoColumnsSpecifiedForTable extends Exception implements PlatformException
{
    public static function new(string $tableName): self
    {
        return new self(sprintf('No columns specified for table "%s".', $tableName));
    }
}

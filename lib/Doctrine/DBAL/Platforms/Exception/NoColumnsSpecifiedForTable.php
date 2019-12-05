<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\DBALException;
use function sprintf;

final class NoColumnsSpecifiedForTable extends DBALException implements PlatformException
{
    public static function new(string $tableName) : self
    {
        return new self(sprintf('No columns specified for table "%s".', $tableName));
    }
}

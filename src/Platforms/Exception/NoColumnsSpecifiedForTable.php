<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use LogicException;

use function sprintf;

final class NoColumnsSpecifiedForTable extends LogicException implements PlatformException
{
    public static function new(OptionallyQualifiedName $tableName): self
    {
        return new self(sprintf('No columns specified for table %s.', $tableName->toString()));
    }
}

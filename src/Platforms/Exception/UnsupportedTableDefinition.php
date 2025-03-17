<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Exception;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use InvalidArgumentException;

use function sprintf;

final class UnsupportedTableDefinition extends InvalidArgumentException implements PlatformException
{
    public static function autoIncrementColumnNotPartOfPrimaryKey(UnqualifiedName $columnName): self
    {
        return new self(sprintf(
            'Auto-increment column %s must be a primary key column.',
            $columnName->toString(),
        ));
    }

    public static function autoIncrementColumnPartOfCompositePrimaryKey(UnqualifiedName $columnName): self
    {
        return new self(sprintf(
            'Auto-increment column %s cannot be part of a composite primary key.',
            $columnName->toString(),
        ));
    }
}

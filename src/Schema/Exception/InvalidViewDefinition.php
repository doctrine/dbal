<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class InvalidViewDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('View name is not set.');
    }

    public static function sqlNotSet(OptionallyQualifiedName $viewName): self
    {
        return new self(sprintf('SQL is not set for view %s.', $viewName->toString()));
    }
}

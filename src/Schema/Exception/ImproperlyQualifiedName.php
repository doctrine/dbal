<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use InvalidArgumentException;

use function sprintf;

class ImproperlyQualifiedName extends InvalidArgumentException implements SchemaException
{
    public static function fromUnqualifiedName(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Schema uses qualified names, but %s is unqualified.', $name->toString()));
    }

    public static function fromQualifiedName(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Schema uses unqualified names, but %s is qualified.', $name->toString()));
    }
}

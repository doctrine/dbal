<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class UnsupportedName extends LogicException implements SchemaException
{
    public static function fromQualifiedName(
        OptionallyQualifiedName $name,
        string $methodName,
    ): self {
        return new self(sprintf(
            "%s() doesn't support introspection of objects with qualified names. %s given.",
            $methodName,
            $name->toString(),
        ));
    }

    public static function fromNonNullSchemaName(string $schemaName, string $methodName): self
    {
        return new self(sprintf('%s() does not accept schema names, "%s" given.', $methodName, $schemaName));
    }

    public static function fromNullSchemaName(string $methodName): self
    {
        return new self(sprintf('%s() requires a schema name, null given.', $methodName));
    }
}

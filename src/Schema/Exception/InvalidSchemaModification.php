<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

/** @internal */
final class InvalidSchemaModification extends LogicException implements SchemaException
{
    public static function schemaConfigMustBeSetBeforeAddingObjects(): self
    {
        return new self(
            'The schema config must be set before any tables or sequences are added to the editor.',
        );
    }

    public static function tableAlreadyExists(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Table %s already exists in the schema.', $name->toString()));
    }

    public static function tableDoesNotExist(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Table %s does not exist in the schema.', $name->toString()));
    }

    public static function sequenceAlreadyExists(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Sequence %s already exists in the schema.', $name->toString()));
    }

    public static function sequenceDoesNotExist(OptionallyQualifiedName $name): self
    {
        return new self(sprintf('Sequence %s does not exist in the schema.', $name->toString()));
    }
}

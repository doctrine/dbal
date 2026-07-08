<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Collections\Exception\ObjectAlreadyExists;
use Doctrine\DBAL\Schema\Collections\Exception\ObjectDoesNotExist;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

/** @internal */
final class InvalidTableModification extends LogicException implements SchemaException
{
    public static function columnAlreadyExists(
        ?OptionallyQualifiedName $tableName,
        ObjectAlreadyExists $previous,
    ): self {
        return new self(sprintf(
            'Column %s already exists on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function columnDoesNotExist(
        ?OptionallyQualifiedName $tableName,
        ObjectDoesNotExist $previous,
    ): self {
        return new self(sprintf(
            'Column %s does not exist on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function indexAlreadyExists(
        ?OptionallyQualifiedName $tableName,
        ObjectAlreadyExists $previous,
    ): self {
        return new self(sprintf(
            'Index %s already exists on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function indexDoesNotExist(
        ?OptionallyQualifiedName $tableName,
        ObjectDoesNotExist $previous,
    ): self {
        return new self(sprintf(
            'Index %s does not exist on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function primaryKeyConstraintAlreadyExists(?OptionallyQualifiedName $tableName): self
    {
        return new self(sprintf(
            'Primary key constraint already exists on table %s.',
            self::formatTableName($tableName),
        ));
    }

    public static function primaryKeyConstraintDoesNotExist(?OptionallyQualifiedName $tableName): self
    {
        return new self(sprintf(
            'Primary key constraint does not exist on table %s.',
            self::formatTableName($tableName),
        ));
    }

    public static function uniqueConstraintAlreadyExists(
        ?OptionallyQualifiedName $tableName,
        ObjectAlreadyExists $previous,
    ): self {
        return new self(sprintf(
            'Unique constraint %s already exists on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function uniqueConstraintDoesNotExist(
        ?OptionallyQualifiedName $tableName,
        ObjectDoesNotExist $previous,
    ): self {
        return new self(sprintf(
            'Unique constraint %s does not exist on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function foreignKeyConstraintAlreadyExists(
        ?OptionallyQualifiedName $tableName,
        ObjectAlreadyExists $previous,
    ): self {
        return new self(sprintf(
            'Foreign key constraint %s already exists on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    public static function foreignKeyConstraintDoesNotExist(
        ?OptionallyQualifiedName $tableName,
        ObjectDoesNotExist $previous,
    ): self {
        return new self(sprintf(
            'Foreign key constraint %s does not exist on table %s.',
            $previous->getObjectName()->toString(),
            self::formatTableName($tableName),
        ), previous: $previous);
    }

    private static function formatTableName(?OptionallyQualifiedName $tableName): string
    {
        return $tableName === null ? '<unnamed>' : $tableName->toString();
    }
}

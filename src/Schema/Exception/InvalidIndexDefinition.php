<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function gettype;
use function implode;
use function is_object;
use function sprintf;

final class InvalidIndexDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('Index name is not set.');
    }

    public static function columnsNotSet(): self
    {
        return new self('Index column names are not set.');
    }

    public static function fromInvalidColumnLengthType(mixed $length): self
    {
        return new self(sprintf(
            'Indexed column length must be a positive integer, %s given.',
            is_object($length) ? $length::class : gettype($length),
        ));
    }

    public static function fromNonPositiveColumnLength(int $length): self
    {
        return new self(sprintf('Indexed column length must be a positive integer, %d given.', $length));
    }

    /** @param non-empty-list<string> $flags */
    public static function fromInvalidFlags(UnqualifiedName $name, array $flags): self
    {
        return new self(sprintf(
            'Index %s has invalid flags: %s.',
            $name->toString(),
            implode(', ', $flags),
        ));
    }

    /** @param non-empty-list<string> $options */
    public static function fromInvalidOptions(UnqualifiedName $name, array $options): self
    {
        return new self(sprintf(
            'Index %s has invalid options: %s.',
            $name->toString(),
            implode(', ', $options),
        ));
    }

    public static function fromNonClusteredClustered(UnqualifiedName $name): self
    {
        return new self(sprintf(
            'Index %s has cannot have both the "clustered" and "nonclustered".',
            $name->toString(),
        ));
    }

    /** @param non-empty-list<string> $flags */
    public static function fromMutuallyExclusiveFlags(UnqualifiedName $name, array $flags): self
    {
        return new self(sprintf(
            'Index %s has mutually exclusive flags: %s.',
            $name->toString(),
            implode(', ', $flags),
        ));
    }

    public static function fromSpatialIndexWithLength(UnqualifiedName $name): self
    {
        return new self(sprintf(
            'Index %s is spatial and cannot have column lengths specified.',
            $name->toString(),
        ));
    }

    public static function fromClusteredIndex(UnqualifiedName $name, IndexType $type): self
    {
        return new self(sprintf(
            'Index %s is of type %s and cannot be clustered.',
            $name->toString(),
            $type->name,
        ));
    }

    public static function fromPartialClusteredIndex(UnqualifiedName $name): self
    {
        return new self(sprintf(
            'Index %s is partial and cannot be clustered.',
            $name->toString(),
        ));
    }

    public static function fromPartialIndex(UnqualifiedName $name, IndexType $type): self
    {
        return new self(sprintf(
            'Index %s is of type %s and cannot be partial.',
            $name->toString(),
            $type->name,
        ));
    }

    public static function fromEmptyPredicate(UnqualifiedName $name): self
    {
        return new self(sprintf('Index %s has empty predicate.', $name->toString()));
    }
}

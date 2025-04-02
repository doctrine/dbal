<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

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

    public static function invalidColumnLength(mixed $length): self
    {
        return new self(sprintf(
            'Indexed column length must be an integer, %s given.',
            is_object($length) ? $length::class : gettype($length),
        ));
    }

    public static function fromPrimaryIndex(): self
    {
        return new self('Primary indexes are not supported.');
    }
}

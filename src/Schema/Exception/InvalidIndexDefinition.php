<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function get_debug_type;
use function sprintf;

final class InvalidIndexDefinition extends LogicException implements SchemaException
{
    public static function nameNotSet(): self
    {
        return new self('Index name is not set.');
    }

    public static function columnsNotSet(UnqualifiedName $indexName): self
    {
        return new self(sprintf('Columns are not set for index %s.', $indexName->toString()));
    }

    public static function fromNonPositiveColumnLength(UnqualifiedName $columnName, int $length): self
    {
        return new self(sprintf(
            'Indexed column length must be a positive integer, %d given for column %s.',
            $length,
            $columnName->toString(),
        ));
    }

    public static function fromInvalidBoundingBox(float $minX, float $minY, float $maxX, float $maxY): self
    {
        return new self(sprintf(
            'The minimum coordinates of a bounding box must be finite and lower than its maximum coordinates,'
                . ' (%s, %s, %s, %s) given.',
            $minX,
            $minY,
            $maxX,
            $maxY,
        ));
    }

    public static function fromMalformedBoundingBox(mixed $boundingBox): self
    {
        return new self(sprintf(
            'The bounding box of an index must be a list of 4 numbers (minimum X, minimum Y, maximum X, maximum Y),'
                . ' %s given.',
            get_debug_type($boundingBox),
        ));
    }

    public static function fromBoundingBoxOfNonSpatialIndex(string $indexName): self
    {
        return new self(sprintf('Only spatial indexes can have a bounding box, %s is not spatial.', $indexName));
    }
}

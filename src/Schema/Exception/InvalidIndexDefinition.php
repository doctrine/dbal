<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function gettype;
use function is_object;
use function sprintf;

final class InvalidIndexDefinition extends LogicException implements SchemaException
{
    public static function columnNamesNotSet(): self
    {
        return new self('Index column names are not set.');
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

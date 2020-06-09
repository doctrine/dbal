<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\SQLParserUtilsException;

use function sprintf;

/**
 * @psalm-immutable
 */
final class MissingArrayParameterType extends SQLParserUtilsException
{
    public static function new(string $paramName): self
    {
        return new self(sprintf('Type of array parameter "%s" is missing.', $paramName));
    }
}

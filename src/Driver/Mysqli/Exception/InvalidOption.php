<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function gettype;
use function is_scalar;
use function sprintf;
use function strval;

/** @internal */
final class InvalidOption extends AbstractException
{
    public static function fromOption(int $option, mixed $value): self
    {
        $stringValue = is_scalar($value) || $value === null ? strval($value) : gettype($value);

        return new self(
            sprintf('Failed to set option %d with value "%s"', $option, $stringValue),
        );
    }
}

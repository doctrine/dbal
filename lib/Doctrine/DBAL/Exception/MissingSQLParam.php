<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\SQLParserUtilsException;
use function sprintf;

final class MissingSQLParam extends SQLParserUtilsException
{
    public static function new(string $paramName) : self
    {
        return new self(
            sprintf('Value for :%1$s not found in params array. Params array key should be "%1$s"', $paramName)
        );
    }
}

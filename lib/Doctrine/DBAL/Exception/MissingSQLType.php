<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\SQLParserUtilsException;
use function sprintf;

final class MissingSQLType extends SQLParserUtilsException
{
    public static function new(string $typeName) : self
    {
        return new self(
            sprintf('Value for :%1$s not found in types array. Types array key should be "%1$s"', $typeName)
        );
    }
}

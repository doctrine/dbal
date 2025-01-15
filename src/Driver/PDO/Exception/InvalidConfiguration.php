<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\Exception;

use Doctrine\DBAL\Driver\AbstractException;

use function get_debug_type;
use function sprintf;

final class InvalidConfiguration extends AbstractException
{
    public static function notAStringOrNull(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The %s configuration parameter is expected to be either a string or null, got %s.',
            $key,
            get_debug_type($value),
        ));
    }
}

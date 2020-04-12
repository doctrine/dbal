<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

/**
 * @internal
 */
final class HostRequired extends MysqliException
{
    public static function forPersistentConnection() : self
    {
        return new self('The "host" parameter is required for a persistent connection');
    }
}
